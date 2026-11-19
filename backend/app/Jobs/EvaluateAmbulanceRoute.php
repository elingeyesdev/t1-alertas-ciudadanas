<?php

namespace App\Jobs;

use App\Enums\StateType;
use App\Models\Dispatch;
use App\Services\AmbulanceProximity;
use App\Services\AmbulanceAlerts;
use App\Services\AmbulanceTracking;
use App\Services\DispatchRouteStore;
use App\Services\EmergencyRoutingService;
use DomainException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class EvaluateAmbulanceRoute implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 25;

    public function __construct(public int $dispatchId) {}

    public function handle(
        AmbulanceTracking $tracking,
        AmbulanceProximity $proximity,
        EmergencyRoutingService $routing,
        DispatchRouteStore $routes,
        AmbulanceAlerts $alerts,
    ): void {
        $cache = $tracking->cache();
        $key = fn (string $field) => $tracking->key($this->dispatchId, $field);

        $cache->lock($key('evaluation'), 30)->get(function () use ($cache, $key, $proximity, $routing, $routes, $alerts) {
            $dispatch = Dispatch::with('report')->find($this->dispatchId);
            $position = $cache->get($key('position'));
            if ($dispatch?->state !== StateType::EnCurso || ! $position
                || strtotime($position['captured_at']) < now()->subSeconds(30)->timestamp) {
                return;
            }

            $cache->forget($key('candidate_count'));
            $route = DB::table('dispatch_routes')->where('dispatch_id', $dispatch->id)->where('status', 'active')->first();
            $progress = $route ? $proximity->progress($route->id, $position) : null;
            if ($progress && $progress->lateral_m <= 65) {
                $cache->forget($key('deviation'));
                $count = $this->evaluateCandidates($proximity, $alerts, $route, $progress, $position);
                $cache->put($key('candidate_count'), $count, 15);

                return;
            }

            if ($route) {
                $deviation = $cache->get($key('deviation'), ['count' => 0, 'sample' => '']);
                if ($deviation['sample'] !== $position['captured_at']) {
                    $deviation = ['count' => $deviation['count'] + 1, 'sample' => $position['captured_at']];
                    $cache->put($key('deviation'), $deviation, 30);
                }
                if ($deviation['count'] < 3) {
                    return;
                }
            }

            $report = $dispatch->report;
            if (! $report || $report->trashed() || ! $cache->add($key('directions_attempt'), true, 45)) {
                return;
            }

            try {
                $result = $routing->calculate(
                    $position['longitude'], $position['latitude'], (float) $report->longitude, (float) $report->latitude,
                );
                $version = $routes->replace($dispatch->id, $result);
                $cache->forget($key('deviation'));
            } catch (DomainException) {
                // The dispatch was closed while Directions was running.
            } catch (RuntimeException|ValidationException $error) {
                Log::warning('No se pudo actualizar la ruta de la ambulancia.', [
                    'dispatch_id' => $this->dispatchId,
                    'error_type' => $error::class,
                ]);
            }

            if (isset($version) && strtotime($position['captured_at']) >= now()->subSeconds(30)->timestamp) {
                $current = DB::table('dispatch_routes')->where('dispatch_id', $dispatch->id)
                    ->where('route_version', $version)->where('status', 'active')->first();
                if ($current && ($projection = $proximity->progress($current->id, $position)) && $projection->lateral_m <= 65) {
                    $count = $this->evaluateCandidates($proximity, $alerts, $current, $projection, $position);
                    $cache->put($key('candidate_count'), $count, 15);
                }
            }
        });
    }

    private function evaluateCandidates(AmbulanceProximity $proximity, AmbulanceAlerts $alerts, object $route, object $progress, array $position): int
    {
        $speed = $route->duration_s > 0 ? $route->distance_m / $route->duration_s : 0;
        if ($progress->ambiguous || $speed < 1 || $speed > 55) {
            return 0;
        }

        $candidates = $proximity->candidates($route->id, $progress->fraction, $speed, $position);
        if ($candidates && config('alertas.ambulance.notifications_enabled')) {
            $alerts->record($route, $candidates, $position['captured_at']);
        }

        return count($candidates);
    }
}
