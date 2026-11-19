<?php

namespace App\Services;

use App\Models\TrustCircleInvite;
use App\Models\TrustContact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * HU-25 Círculo de confianza (estilo Life360).
 * Cada persona tiene hasta 5 contactos. La ubicación NO se comparte de forma
 * constante: solo se envía cuando la persona activa el SOS (ver alert()).
 */
class TrustCircle
{
    public const MAX_CONTACTS = 5;

    public const INVITE_HOURS = 48;

    public function __construct(private readonly FcmService $fcm) {}

    /** Mi círculo y los círculos de otras personas a los que pertenezco. */
    public function overview(User $user): array
    {
        $contacts = TrustContact::with('contactUser')->where('owner_id', $user->id)->orderBy('id')->get();
        $memberships = TrustContact::with('owner')->where('contact_user_id', $user->id)->orderBy('id')->get();

        return [
            'max_contacts' => self::MAX_CONTACTS,
            'contacts' => $contacts->map(fn (TrustContact $c) => $this->contactData($c))->all(),
            'memberships' => $memberships->map(fn (TrustContact $c) => [
                'id' => $c->id,
                'owner_name' => trim(($c->owner?->first_name ?? '').' '.($c->owner?->last_name ?? '')),
                'joined_at' => $c->created_at?->toISOString(),
            ])->all(),
        ];
    }

    public function addManual(User $owner, string $name, string $phone): TrustContact
    {
        $phone = $this->normalizePhone($phone);

        return DB::transaction(function () use ($owner, $name, $phone) {
            $this->lockOwner($owner);
            $this->ensureRoom($owner);
            if ($this->digits($phone) === $this->digits($owner->phone)) {
                throw ValidationException::withMessages(['phone' => 'No puedes agregarte a ti mismo.']);
            }
            $this->ensurePhoneIsNew($owner, $phone);

            return TrustContact::create(['owner_id' => $owner->id, 'name' => $name, 'phone' => $phone]);
        });
    }

    public function update(User $owner, int $id, array $data): TrustContact
    {
        $contact = $this->ownedContact($owner, $id);
        if (isset($data['name'])) {
            $contact->name = $data['name'];
        }
        // El teléfono de un contacto con la app es el de su cuenta: no se edita aquí.
        if (isset($data['phone']) && ! $contact->hasApp()) {
            $phone = $this->normalizePhone($data['phone']);
            if ($phone !== $contact->phone) {
                $this->ensurePhoneIsNew($owner, $phone, $contact->id);
            }
            $contact->phone = $phone;
        }
        $contact->save();

        return $contact;
    }

    public function remove(User $owner, int $id): void
    {
        $this->ownedContact($owner, $id)->delete();
    }

    /** Salir del círculo de otra persona. */
    public function leave(User $member, int $id): void
    {
        $membership = TrustContact::where('id', $id)->where('contact_user_id', $member->id)->first();
        abort_unless($membership, 404, 'No perteneces a ese círculo');
        $membership->delete();
    }

    public function createInvite(User $owner): TrustCircleInvite
    {
        $this->ensureRoom($owner);

        do {
            // Sin letras que se confunden (0/O, 1/I)
            $code = strtoupper(Str::of(Str::random(20))->replaceMatches('/[^A-HJ-NP-Z2-9]/i', '')->substr(0, 6));
        } while (strlen($code) < 6 || TrustCircleInvite::where('code', $code)->exists());

        return TrustCircleInvite::create([
            'owner_id' => $owner->id,
            'code' => $code,
            'expires_at' => now()->addHours(self::INVITE_HOURS),
        ]);
    }

    /** Aceptar una invitación: quien la acepta entra al círculo de quien la creó. */
    public function join(User $member, string $code): TrustContact
    {
        return DB::transaction(function () use ($member, $code) {
            $invite = TrustCircleInvite::where('code', strtoupper(trim($code)))->lockForUpdate()->first();
            if (! $invite || $invite->used_at || $invite->expires_at->isPast()) {
                throw ValidationException::withMessages(['code' => 'El código no es válido o ya venció.']);
            }
            $owner = $invite->owner;
            if (! $owner || $owner->trashed()) {
                throw ValidationException::withMessages(['code' => 'El código no es válido o ya venció.']);
            }
            if ($owner->id === $member->id) {
                throw ValidationException::withMessages(['code' => 'Ese código es tuyo: compártelo con tus contactos.']);
            }

            $this->lockOwner($owner);
            if (TrustContact::where('owner_id', $owner->id)->where('contact_user_id', $member->id)->exists()) {
                throw ValidationException::withMessages(['code' => 'Ya estás en ese círculo.']);
            }
            // Si ya estaba agregado a mano con su número, pasa a ser contacto con la app.
            $manual = TrustContact::where('owner_id', $owner->id)->whereNull('contact_user_id')->get()
                ->first(fn (TrustContact $c) => $this->digits($c->phone) === $this->digits($member->phone));
            if (! $manual) {
                $this->ensureRoom($owner);
            }

            $contact = $manual ?? new TrustContact(['owner_id' => $owner->id]);
            $contact->fill([
                'contact_user_id' => $member->id,
                'name' => trim($member->first_name.' '.$member->last_name),
                'phone' => $member->phone,
            ])->save();

            $invite->update(['used_at' => now(), 'used_by' => $member->id]);

            return $contact;
        });
    }

    /**
     * Avisa a todo el círculo que la persona pidió ayuda, con su ubicación.
     * Con la app: push. Sin la app: se devuelve el enlace de WhatsApp para que
     * el teléfono de quien pide ayuda lo abra (gratis, sin SMS).
     *
     * @return array{pushed: int, whatsapp: array<int, array{name: string, phone: string, url: string}>}
     */
    public function alert(User $owner, float $latitude, float $longitude, ?int $reportId = null): array
    {
        $name = trim($owner->first_name.' '.$owner->last_name) ?: 'Un contacto';
        $mapUrl = sprintf('https://maps.google.com/?q=%.6F,%.6F', $latitude, $longitude);
        $text = "🚨 {$name} activó el botón de pánico y necesita ayuda. Su ubicación: {$mapUrl}";

        $contacts = TrustContact::with('contactUser')->where('owner_id', $owner->id)->get();
        $tokens = $contacts->map(fn (TrustContact $c) => $c->contactUser?->trashed() ? null : $c->contactUser?->fcm_token)
            ->filter()->values()->all();

        $pushed = 0;
        if ($tokens) {
            try {
                $pushed = $this->fcm->sendToTokens($tokens, "🚨 {$name} necesita ayuda", 'Activó el botón de pánico. Toca para ver su ubicación.', [
                    'type' => 'trust_circle_sos',
                    'latitude' => (string) $latitude,
                    'longitude' => (string) $longitude,
                    'name' => $name,
                    'reportId' => (string) ($reportId ?? ''),
                ])['successCount'];
            } catch (Throwable $e) {
                Log::warning('No se pudo avisar al círculo de confianza por push.', ['error' => $e->getMessage()]);
            }
        }

        $whatsapp = $contacts->reject(fn (TrustContact $c) => $c->hasApp() && ! blank($c->contactUser?->fcm_token))
            ->map(fn (TrustContact $c) => [
                'name' => $c->name,
                'phone' => $c->phone,
                'url' => 'https://wa.me/'.$this->whatsappNumber($c->phone).'?text='.rawurlencode($text),
            ])->values()->all();

        return ['pushed' => $pushed, 'whatsapp' => $whatsapp];
    }

    public function contactData(TrustContact $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'phone' => $c->phone,
            'has_app' => $c->hasApp(),
            'created_at' => $c->created_at?->toISOString(),
        ];
    }

    private function ownedContact(User $owner, int $id): TrustContact
    {
        $contact = TrustContact::where('id', $id)->where('owner_id', $owner->id)->first();
        abort_unless($contact, 404, 'Contacto no encontrado');

        return $contact;
    }

    private function ensureRoom(User $owner): void
    {
        if (TrustContact::where('owner_id', $owner->id)->count() >= self::MAX_CONTACTS) {
            throw ValidationException::withMessages([
                'contacts' => 'Tu círculo ya tiene '.self::MAX_CONTACTS.' contactos. Quita uno para agregar otro.',
            ]);
        }
    }

    private function ensurePhoneIsNew(User $owner, string $phone, ?int $ignoreId = null): void
    {
        $exists = TrustContact::where('owner_id', $owner->id)
            ->when($ignoreId, fn ($q) => $q->where('id', '<>', $ignoreId))
            ->get()->contains(fn (TrustContact $c) => $this->digits($c->phone) === $this->digits($phone));
        if ($exists) {
            throw ValidationException::withMessages(['phone' => 'Ese teléfono ya está en tu círculo.']);
        }
    }

    /** Evita que dos peticiones simultáneas superen el límite de 5. */
    private function lockOwner(User $owner): void
    {
        User::whereKey($owner->id)->lockForUpdate()->first();
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        $digits = $this->digits($phone);

        return str_starts_with($phone, '+') ? '+'.$digits : $digits;
    }

    private function digits(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone);
    }

    /** wa.me necesita el número con código de país; los de 8 dígitos se toman como de Bolivia (591). */
    private function whatsappNumber(string $phone): string
    {
        $digits = $this->digits($phone);

        return strlen($digits) === 8 ? '591'.$digits : $digits;
    }
}
