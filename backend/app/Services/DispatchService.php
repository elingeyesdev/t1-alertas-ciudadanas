<?php

namespace App\Services;

use App\Enums\StateType;
use App\Http\Resources\DispatchResource;
use App\Models\Dispatch;
use App\Models\EmergencyStation;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DispatchService
{
    public function create(int $reportId, int $destinationId, string $userId): DispatchResource
    {
        if (! Report::whereKey($reportId)->exists()) {
            abort(404, 'Reporte no encontrado');
        }
        if (! EmergencyStation::whereKey($destinationId)->exists()) {
            abort(404, 'Estacion de emergencia no encontrada');
        }
        if (! User::whereKey($userId)->exists()) {
            abort(404, 'Usuario no encontrado');
        }

        $dispatch = Dispatch::create([
            'report_id' => $reportId,
            'destination_id' => $destinationId,
            'attended_by' => $userId,
            'state' => StateType::EnCurso,
            'recorded_at' => now(),
        ]);

        return new DispatchResource($dispatch->load(['report.type', 'report.images.uploadedBy', 'destination', 'attendedBy']));
    }

    public function updateState(int $id, string $state, User $actor): DispatchResource
    {
        $dispatch = DB::transaction(function () use ($id, $state, $actor) {
            $dispatch = Dispatch::query()->lockForUpdate()->findOrFail($id);
            abort_unless($dispatch->attended_by === $actor->id || $actor->hasRole('admin'), 403);
            $dispatch->update(['state' => StateType::from($state)]);

            if ($dispatch->state !== StateType::EnCurso) {
                DB::table('dispatch_routes')->where('dispatch_id', $id)->where('status', 'active')
                    ->update(['status' => 'completed', 'updated_at' => now()]);
            }

            return $dispatch;
        });

        return new DispatchResource($dispatch);
    }
}
