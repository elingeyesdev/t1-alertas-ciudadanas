<?php

namespace App\Services;

use App\Models\MissingCase;
use App\Models\MissingCaseContribution;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class MissingCases
{
    public function __construct(
        private readonly FcmService $fcm,
        private readonly LocationPrivacy $locations,
    ) {}

    public function create(array $data, User $actor, bool $publish = false): MissingCase
    {
        $case = new MissingCase(Arr::except($data, ['photos', 'latitude', 'longitude', 'radius_m']));
        $case->forceFill($publish ? $this->publication($data, $actor) : Arr::only($data, ['latitude', 'longitude']) + [
            'status' => 'pending',
            'submitted_by' => $actor->id,
        ]);
        $this->withStoredMedia($data['photos'], function (array $photos) use ($case): void {
            $case->photos = $photos;
            $case->save();
        });

        if ($publish) {
            $this->notify($case);
        }

        return $case;
    }

    public function approve(MissingCase $case, array $zone, User $admin): void
    {
        $this->transition($case, 'pending', $this->publication($zone, $admin));
        $case->refresh();
        $ownerToken = $this->notifySubmitter($case, 'published', 'Tu caso fue aprobado', 'Ya está publicado en Desaparecidos.');
        $this->notify($case, $ownerToken);
    }

    public function reject(MissingCase $case): void
    {
        $this->transition($case, 'pending', ['status' => 'rejected']);
        $this->notifySubmitter($case, 'rejected', 'Tu caso fue revisado', 'La sugerencia fue rechazada. Consulta su estado en Mis casos.');
    }

    public function markFound(MissingCase $case): void
    {
        $this->transition($case, 'published', ['status' => 'found', 'found_at' => now()]);
        $this->notifySubmitter($case, 'found', 'Tu caso fue marcado como encontrado', 'Consulta el estado y los aportes en Mis casos.');
    }

    public function contribute(MissingCase $case, array $data, User $author): MissingCaseContribution
    {
        abort_unless($case->status === 'published', 409, 'El caso ya está cerrado.');

        $files = $data['photos'] ?? [];
        if (isset($data['video'])) {
            $files['video'] = $data['video'];
        }
        $contribution = $this->withStoredMedia($files, function (array $paths) use ($case, $data, $author) {
            return DB::transaction(function () use ($case, $data, $author, $paths) {
                $current = MissingCase::query()->lockForUpdate()->findOrFail($case->id);
                abort_unless($current->status === 'published', 409, 'El caso ya está cerrado.');

                return $current->contributions()->create([
                    'user_id' => $author->id,
                    'message' => $data['message'] ?? null,
                    'photos' => Arr::except($paths, ['video']),
                    'video_path' => $paths['video'] ?? null,
                ])->setRelation('author', $author);
            });
        });

        if ($case->submitted_by !== $author->id) {
            $this->notifySubmitter($case, 'contribution', 'Nuevo aporte en tu caso', 'Abre Desaparecidos para ver la información.', $contribution->id, $author->fcm_token);
        }

        return $contribution;
    }

    public function photo(MissingCase|MissingCaseContribution $case, int $index): StreamedResponse
    {
        $path = $case->photos[$index] ?? null;
        $disk = Storage::disk('local');
        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, headers: [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function video(MissingCaseContribution $item): BinaryFileResponse
    {
        $disk = Storage::disk('local');
        abort_unless($item->video_path && $disk->exists($item->video_path), 404);

        return response()->file($disk->path($item->video_path), [
            'Content-Type' => 'video/mp4',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function withStoredMedia(array $files, callable $save): mixed
    {
        $disk = Storage::disk('local');
        $paths = [];

        try {
            foreach ($files as $index => $file) {
                $path = $disk->putFile('missing-cases/files', $file);
                if ($path === false) {
                    throw new RuntimeException('No se pudo guardar el archivo del caso.');
                }
                $paths[$index] = $path;
            }

            return $save($paths);
        } catch (Throwable $error) {
            $disk->delete(array_values($paths));
            throw $error;
        }
    }

    private function publication(array $zone, User $admin): array
    {
        return Arr::only($zone, ['latitude', 'longitude', 'radius_m']) + [
            'status' => 'published',
            'published_by' => $admin->id,
            'published_at' => now(),
        ];
    }

    private function transition(MissingCase $case, string $from, array $changes): void
    {
        $updated = MissingCase::query()->whereKey($case->id)->where('status', $from)->update($changes);
        abort_unless($updated === 1, 409, 'El caso ya cambió de estado. Actualiza la página.');
    }

    private function notifySubmitter(
        MissingCase $case,
        string $update,
        string $title,
        string $body,
        ?int $contributionId = null,
        ?string $excludeToken = null,
    ): ?string {
        $token = null;
        try {
            $token = $case->submitter()->value('fcm_token');
            if ($token && $token !== $excludeToken) {
                $this->fcm->sendToTokens([$token], $title, $body, [
                    'type' => 'missing_case_update',
                    'missingCaseId' => (string) $case->id,
                    'update' => $update,
                ] + ($contributionId === null ? [] : ['contributionId' => (string) $contributionId]));
            }
        } catch (Throwable $error) {
            report($error);
        }

        return $token;
    }

    private function notify(MissingCase $case, ?string $excludeToken = null): void
    {
        try {
            $tokens = $this->locations->nearbyCitizenPushRecipients($case->latitude, $case->longitude, $case->radius_m)
                ->pluck('fcm_token')->filter(fn ($token) => $token && $token !== $excludeToken)->unique()->values()->all();
            if ($tokens === []) {
                return;
            }

            $title = $case->kind === 'person' ? 'Persona desaparecida cerca de ti' : 'Mascota desaparecida cerca de ti';
            $this->fcm->sendToTokens($tokens, $title, 'Abre el muro para ver los datos del caso.', [
                'type' => 'missing_case',
                'missingCaseId' => (string) $case->id,
            ]);
        } catch (Throwable $error) {
            report($error);
        }
    }
}
