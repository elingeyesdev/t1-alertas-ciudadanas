<?php

namespace App\Http\Controllers;

use App\Enums\StateType;
use App\Http\Requests\EmergencyPositionRequest;
use App\Jobs\EvaluateAmbulanceRoute;
use App\Models\Dispatch;
use App\Services\AmbulanceTracking;
use App\Services\CaseTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AmbulanceTrackingController extends Controller
{
    public function unitPosition(EmergencyPositionRequest $request, Dispatch $dispatch, AmbulanceTracking $tracking, CaseTracking $cases)
    {
        $user = $request->user();
        abort_unless(
            $dispatch->attended_by === $user->id && $user->authorityProfile?->profile_type?->value === 'paramedico',
            403,
        );
        abort_unless($dispatch->state === StateType::EnCurso, 409);

        if ($dispatch->departed_at === null) {
            $cases->markEnRoute($dispatch, $user);
        }

        $position = $request->position();
        $cache = $tracking->cache();
        $key = $tracking->key($dispatch->id, 'position');
        $accepted = $cache->lock($key.':write', 5)->block(2, function () use ($cache, $key, $position) {
            $current = $cache->get($key);
            if (! $current || $position['captured_at'] > $current['captured_at']) {
                $cache->put($key, $position, 60);

                return true;
            }

            return false;
        });

        if ($accepted && $cache->add($tracking->key($dispatch->id, 'scheduled'), true, 5)) {
            EvaluateAmbulanceRoute::dispatch($dispatch->id)
                ->onConnection(config('alertas.ambulance.queue_connection'))
                ->onQueue('ambulance');
        }

        return response()->noContent();
    }

    public function consent(Request $request)
    {
        $request->validate(['enabled' => ['required', 'boolean']]);
        $userId = $request->user()->id;
        $locations = DB::table('ambulance_alert_locations');
        if ($request->boolean('enabled')) {
            $locations->insertOrIgnore(['user_id' => $userId]);
        } else {
            $locations->where('user_id', $userId)->delete();
        }

        return response()->noContent();
    }

    public function consentStatus(Request $request)
    {
        return response()->json([
            'enabled' => DB::table('ambulance_alert_locations')->where('user_id', $request->user()->id)->exists(),
        ]);
    }

    public function receipt(Request $request, string $alert)
    {
        $data = $request->validate(['event' => ['required', 'in:foreground,opened']]);
        $column = $data['event'] === 'foreground' ? 'foreground_received_at' : 'opened_at';
        DB::table('ambulance_alerts')->where('id', $alert)->where('user_id', $request->user()->id)
            ->whereNull($column)->update([$column => now()]);

        return response()->noContent();
    }

    public function citizenPosition(EmergencyPositionRequest $request)
    {
        $position = $request->position();
        DB::update(<<<'SQL'
            UPDATE ambulance_alert_locations
            SET position = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                accuracy_m = ?, captured_at = ?
            WHERE user_id = ? AND (captured_at IS NULL OR captured_at < ?)
            SQL, [
            $position['longitude'], $position['latitude'], $position['accuracy_m'],
            $position['captured_at'], $request->user()->id, $position['captured_at'],
        ]);

        return response()->noContent();
    }
}
