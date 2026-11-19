<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AmbulanceAlerts
{
    public function record(object $route, array $candidates, string $capturedAt): void
    {
        $now = now();
        DB::transaction(function () use ($route, $candidates, $capturedAt, $now) {
            foreach ($candidates as $candidate) {
                $id = (string) Str::uuid();
                $inserted = DB::table('ambulance_alerts')->insertOrIgnore([
                    'id' => $id,
                    'dispatch_id' => $route->dispatch_id,
                    'user_id' => $candidate->user_id,
                    'route_version' => $route->route_version,
                    'eta_s' => (int) ceil($candidate->eta_s),
                    'gps_captured_at' => $capturedAt,
                    'created_at' => $now,
                ]);
                if ($inserted) {
                    DB::table('ambulance_alert_outbox')->insert([
                        'alert_id' => $id,
                        'available_at' => $now,
                        'expires_at' => $now->copy()->addSeconds($candidate->eta_s == 0 ? 15 : min(20, (int) floor($candidate->eta_s))),
                    ]);
                }
            }
        }, 3);
    }
}
