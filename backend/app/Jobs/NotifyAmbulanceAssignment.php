<?php

namespace App\Jobs;

use App\Enums\StateType;
use App\Models\Dispatch;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyAmbulanceAssignment implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $dispatchId) {}

    public function handle(FcmService $fcm): void
    {
        $dispatch = Dispatch::with('attendedBy.authorityProfile')->find($this->dispatchId);
        $user = $dispatch?->attendedBy;
        if ($dispatch?->state !== StateType::EnCurso || ! $user || $user->trashed()
            || $user->authorityProfile?->profile_type?->value !== 'paramedico'
            || blank($user->fcm_token) || ! $fcm->enabled()) {
            return;
        }

        $fcm->sendToTokens(
            [$user->fcm_token],
            'Nuevo despacho de ambulancia',
            'Toca para ver la alerta en el mapa e iniciar el viaje.',
            ['type' => 'ambulance_assignment', 'dispatch_id' => $dispatch->id],
        );
    }
}
