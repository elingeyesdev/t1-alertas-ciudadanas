<?php

namespace App\Console\Commands;

use App\Services\AmbulanceProximity;
use App\Services\AmbulanceTracking;
use App\Services\FcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeliverAmbulanceAlerts extends Command
{
    protected $signature = 'ambulance:deliver-alerts';

    protected $description = 'Entrega avisos vigentes registrados en la bandeja de salida';

    public function handle(FcmService $fcm, AmbulanceTracking $tracking, AmbulanceProximity $proximity): int
    {
        $outbox = DB::table('ambulance_alert_outbox');
        $outbox->clone()->where('status', 'sending')->where('claimed_at', '<', now()->subMinute())
            ->update(['status' => 'uncertain']);
        $outbox->clone()->where('status', 'pending')->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
        if (! config('alertas.ambulance.notifications_enabled') || ! $fcm->enabled()) {
            return self::SUCCESS;
        }

        for ($index = 0; $index < 10; $index++) {
            $item = $this->claim();
            if (! $item) {
                break;
            }

            $alert = DB::table('ambulance_alerts')->where('id', $item->alert_id)->first();
            $token = $alert ? $this->eligibleToken($alert, $tracking, $proximity) : null;
            $status = $token ? $fcm->sendAmbulanceAlert($token, $item->alert_id, strtotime($item->expires_at)) : 'suppressed';
            DB::transaction(function () use ($outbox, $item, $status) {
                $update = ['status' => $status];
                if ($status === 'retry') {
                    $update = $item->attempts < 2
                        ? ['status' => 'pending', 'available_at' => now()->addSeconds(5)]
                        : ['status' => 'rejected'];
                }
                $outbox->clone()->where('alert_id', $item->alert_id)->update($update);
                if ($status === 'sent') {
                    DB::table('ambulance_alerts')->where('id', $item->alert_id)->update(['fcm_accepted_at' => now()]);
                }
            });
        }

        return self::SUCCESS;
    }

    private function claim(): ?object
    {
        return DB::transaction(function () {
            $outbox = DB::table('ambulance_alert_outbox');
            $item = $outbox->clone()->where('status', 'pending')->where('available_at', '<=', now())
                ->where('expires_at', '>', now())->orderBy('expires_at')->lock('for update skip locked')->first();
            if ($item) {
                $item->attempts++;
                $outbox->where('alert_id', $item->alert_id)->update([
                    'status' => 'sending', 'attempts' => $item->attempts, 'claimed_at' => now(),
                ]);
            }

            return $item;
        });
    }

    private function eligibleToken(object $alert, AmbulanceTracking $tracking, AmbulanceProximity $proximity): ?string
    {
        $route = DB::table('dispatch_routes')->where('dispatch_id', $alert->dispatch_id)
            ->where('route_version', $alert->route_version)->where('status', 'active')->first();
        $position = $tracking->cache()->get($tracking->key($alert->dispatch_id, 'position'));
        if (! $route || ! $position || strtotime($position['captured_at']) < now()->subSeconds(30)->timestamp) {
            return null;
        }

        $progress = $proximity->progress($route->id, $position);
        $speed = $route->duration_s > 0 ? $route->distance_m / $route->duration_s : 0;
        if (! $progress || $progress->ambiguous || $progress->lateral_m > 65
            || $speed < 1 || $speed > 55 || ! $proximity->candidates($route->id, $progress->fraction, $speed, $position, $alert->user_id)) {
            return null;
        }

        return DB::table('users')->join('ambulance_alert_locations', 'users.id', '=', 'ambulance_alert_locations.user_id')
            ->where('users.id', $alert->user_id)->whereNull('users.deleted_at')->value('users.fcm_token');
    }
}
