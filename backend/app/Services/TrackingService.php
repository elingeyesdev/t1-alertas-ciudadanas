<?php

namespace App\Services;

use App\Events\TrackingStarted;
use App\Events\TrackingStopped;
use App\Support\SafeBroadcast;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Reemplaza a TrackingsService + TrackingsGateway.
 * Guarda la posición en vivo de las unidades en caché (Redis si
 * TRACKING_CACHE_STORE=redis) y emite eventos por WebSocket (Reverb).
 */
class TrackingService
{
    private const KEY = 'trackings';

    public function save(string $userId, array $data): array
    {
        return $this->store()->lock(self::KEY.':lock', 5)->block(5, function () use ($userId, $data) {
            $all = $this->store()->get(self::KEY, []);
            $all[$userId] = array_merge($data, ['timestamp' => (int) (microtime(true) * 1000)]);
            $this->store()->forever(self::KEY, $all);

            $tracking = ['id' => $userId] + $all[$userId];
            SafeBroadcast::send(new TrackingStarted($tracking));

            return $tracking;
        });
    }

    public function delete(string $userId): void
    {
        $this->store()->lock(self::KEY.':lock', 5)->block(5, function () use ($userId) {
            $all = $this->store()->get(self::KEY, []);
            unset($all[$userId]);
            $this->store()->forever(self::KEY, $all);
        });

        SafeBroadcast::send(new TrackingStopped($userId));
    }

    /** Lista los trackings activos (descarta los que no se actualizan hace rato). */
    public function all(): array
    {
        $minTimestamp = (int) ((microtime(true) - config('alertas.trackings.ttl_seconds')) * 1000);
        $list = [];

        foreach ($this->store()->get(self::KEY, []) as $userId => $data) {
            if (($data['timestamp'] ?? 0) >= $minTimestamp) {
                $list[] = ['id' => (string) $userId] + $data;
            }
        }

        return $list;
    }

    private function store(): Repository
    {
        return Cache::store(config('alertas.trackings.cache_store'));
    }
}
