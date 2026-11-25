<?php

namespace App\Services;

use App\Events\PublicAlertEnded;
use App\Events\PublicAlertPublished;
use App\Http\Resources\PublicAlertResource;
use App\Models\PublicAlert;
use App\Models\User;
use App\Support\Geo;
use App\Support\SafeBroadcast;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * HU-32 Alertas de salud, viaje o desastres naturales.
 * El administrador publica un aviso con título, mensaje, zona (círculo) y
 * vigencia; llega por push a los ciudadanos que están dentro de la zona y
 * se ve en el mapa de la app y del panel mientras está vigente.
 */
class PublicAlerts
{
    public function __construct(private readonly FcmService $fcm) {}

    /**
     * @param  array{category: string, title: string, message: string, latitude: float, longitude: float, radius_m: int, hours: int}  $data
     */
    public function publish(array $data, User $admin): PublicAlert
    {
        $alert = PublicAlert::create([
            'category' => $data['category'],
            'title' => trim($data['title']),
            'message' => trim($data['message']),
            'latitude' => (float) $data['latitude'],
            'longitude' => (float) $data['longitude'],
            'radius_m' => (int) $data['radius_m'],
            'expires_at' => now()->addHours((int) $data['hours']),
            'created_by' => $admin->id,
        ]);

        $alert->forceFill(['notified_count' => $this->notifyZone($alert)])->save();
        SafeBroadcast::send(new PublicAlertPublished((new PublicAlertResource($alert))->resolve()));

        return $alert;
    }

    /** Termina el aviso antes de tiempo: deja de verse en los mapas. */
    public function finish(int $id): PublicAlert
    {
        $alert = PublicAlert::findOrFail($id);
        if (! $alert->isActive()) {
            abort(400, 'El aviso ya no está vigente.');
        }
        $alert->forceFill(['expires_at' => now()])->save();
        SafeBroadcast::send(new PublicAlertEnded($alert->id));

        return $alert;
    }

    public function remove(int $id): void
    {
        $alert = PublicAlert::findOrFail($id);
        $alert->delete();
        SafeBroadcast::send(new PublicAlertEnded($alert->id));
    }

    /** @return Collection<int, PublicAlert> */
    public function active(): Collection
    {
        return PublicAlert::active()->latest()->get();
    }

    /** Ciudadanos dentro del círculo que comparten su ubicación (HU-29) y tienen token push. */
    public function recipients(PublicAlert $alert): Collection
    {
        $query = User::query()
            ->whereHas('role', fn ($role) => $role->where('name', 'usuario'))
            ->where('location_sharing', true)
            ->whereNotNull('fcm_token')
            ->whereNotNull('last_latitude')
            ->whereNotNull('last_longitude');

        return Geo::whereWithin($query, $alert->latitude, $alert->longitude, $alert->radius_m, 'last_latitude', 'last_longitude')->get();
    }

    private function notifyZone(PublicAlert $alert): int
    {
        try {
            $tokens = $this->recipients($alert)->pluck('fcm_token')->filter()->unique()->values()->all();
            if (! $tokens) {
                Log::info('Aviso preventivo sin ciudadanos dentro de la zona', ['public_alert_id' => $alert->id]);

                return 0;
            }

            $this->fcm->sendToTokens(
                $tokens,
                $alert->category->label().': '.$alert->title,
                Str::limit($alert->message, 180),
                ['type' => 'public_alert', 'publicAlertId' => (string) $alert->id, 'category' => $alert->category->value],
            );

            return count($tokens);
        } catch (Throwable $e) {
            Log::error('Error notificando aviso preventivo', ['error' => $e->getMessage()]);

            return 0;
        }
    }
}
