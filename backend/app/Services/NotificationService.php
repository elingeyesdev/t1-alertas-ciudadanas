<?php

namespace App\Services;

use App\Models\Report;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationService
{
    public function __construct(private readonly FcmService $fcm) {}

    /** Notifica a usuarios cercanos (100 m) cuando se crea un reporte. */
    public function notifyNearbyUsers(Report $report): void
    {
        try {
            $query = User::query()
                ->whereNotNull('fcm_token')
                ->whereNotNull('last_latitude')
                ->where('id', '!=', $report->creator_id);

            $users = Geo::whereWithin(
                $query,
                $report->latitude,
                $report->longitude,
                config('alertas.notifications.nearby_radius_m'),
                'last_latitude',
                'last_longitude',
            )->get();

            $tokens = $users->pluck('fcm_token')->filter()->all();
            if (! $tokens) {
                Log::info('No hay usuarios cercanos para notificar');

                return;
            }

            $report->loadMissing('type');
            $this->fcm->sendToTokens(
                $tokens,
                'Nueva alerta: '.($report->type->name ?? 'Incidente'),
                $report->description ?: 'Se ha reportado un incidente cerca de ti.',
                ['reportId' => (string) $report->id],
            );
        } catch (Throwable $e) {
            Log::error('Error notificando usuarios cercanos', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Notifica a usuarios que estén a menos de 250 m de la ruta de una
     * unidad de emergencia. Cooldown de 10 min por (autoridad, usuario).
     *
     * @param  array<int, array{lat: float, lng: float}>  $route
     * @return array{notified: int, skippedCooldown: int}
     */
    public function notifyUsersAlongRoute(array $route, string $excludeUserId, ?string $incidentType = null, ?string $description = null, ?int $reportId = null): array
    {
        $route = array_values(array_filter(
            array_map(fn ($p) => ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']], $route),
            fn ($p) => is_finite($p['lat']) && is_finite($p['lng']),
        ));
        if (count($route) < 2) {
            abort(400, 'Se requieren al menos 2 puntos de ruta');
        }

        $radius = config('alertas.notifications.route_corridor_radius_m');

        // Pre-filtro por un rectángulo que contiene toda la ruta (+ margen del radio)
        $lats = array_column($route, 'lat');
        $lngs = array_column($route, 'lng');
        $centerLat = (min($lats) + max($lats)) / 2;
        $dLat = rad2deg($radius / Geo::EARTH_RADIUS_M);
        $dLng = rad2deg($radius / (Geo::EARTH_RADIUS_M * max(cos(deg2rad($centerLat)), 0.000001)));

        $candidates = User::query()
            ->whereNotNull('fcm_token')
            ->whereNotNull('last_latitude')
            ->where('id', '!=', $excludeUserId)
            ->whereBetween('last_latitude', [min($lats) - $dLat, max($lats) + $dLat])
            ->whereBetween('last_longitude', [min($lngs) - $dLng, max($lngs) + $dLng])
            ->get();

        $tokens = [];
        $skipped = 0;
        $cooldown = now()->addMinutes(config('alertas.notifications.route_cooldown_minutes'));

        foreach ($candidates as $user) {
            if (Geo::distanceToRoute($user->last_latitude, $user->last_longitude, $route) > $radius) {
                continue;
            }
            // Cache::add solo guarda si la clave NO existe => evita spam
            if (! Cache::add("route_notify:{$excludeUserId}:{$user->id}", true, $cooldown)) {
                $skipped++;

                continue;
            }
            $tokens[] = $user->fcm_token;
        }

        if (! $tokens) {
            Log::info("Sin usuarios en corredor de ruta (omitidos por cooldown: {$skipped})");

            return ['notified' => 0, 'skippedCooldown' => $skipped];
        }

        $typeLabel = trim((string) $incidentType) ?: 'Emergencia';
        $this->fcm->sendToTokens(
            $tokens,
            'Unidad de emergencia en camino',
            "Hay una unidad respondiendo a «{$typeLabel}» cerca de tu ubicación. Mantente atento.",
            ['type' => 'route_corridor', 'incidentType' => $typeLabel, 'reportId' => (string) ($reportId ?? '')],
        );

        return ['notified' => count($tokens), 'skippedCooldown' => $skipped];
    }

    public function notifyReportVerified(Report $report): void
    {
        try {
            $creator = $report->creator;
            if (! $creator?->fcm_token) {
                Log::info('Creador sin token FCM — omitiendo notificación de verificación');

                return;
            }

            $this->fcm->sendToTokens(
                [$creator->fcm_token],
                'Reporte verificado',
                "Tu reporte #{$report->id} fue confirmado por una autoridad.",
                ['reportId' => (string) $report->id, 'type' => 'report_verified'],
            );
        } catch (Throwable $e) {
            Log::error('Error notificando verificación de reporte', ['error' => $e->getMessage()]);
        }
    }
}
