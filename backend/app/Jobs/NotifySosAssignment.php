<?php

namespace App\Jobs;

use App\Enums\StateType;
use App\Models\Dispatch;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * HU-24: avisa por push a la unidad que el sistema asignó automáticamente a un SOS.
 * Usa el mismo tipo de mensaje que la asignación de ambulancia, así la app abre
 * el despacho en el mapa para «Iniciar viaje».
 */
class NotifySosAssignment implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $dispatchId) {}

    public function handle(FcmService $fcm): void
    {
        $dispatch = Dispatch::with(['attendedBy', 'report'])->find($this->dispatchId);
        $unit = $dispatch?->attendedBy;
        if ($dispatch?->state !== StateType::EnCurso || ! $unit || $unit->trashed()
            || blank($unit->fcm_token) || ! $fcm->enabled()) {
            return;
        }

        $fcm->sendToTokens(
            [$unit->fcm_token],
            '🚨 SOS asignado a tu unidad',
            ($dispatch->report?->zone ? "Zona {$dispatch->report->zone}. " : '').'Toca para ver la ubicación e iniciar el viaje.',
            ['type' => 'ambulance_assignment', 'dispatch_id' => $dispatch->id, 'sos' => '1'],
        );
    }
}
