<?php

namespace App\Http\Resources;

use App\Support\Dt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DispatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recorded_at' => Dt::iso($this->recorded_at),
            'state' => $this->state?->value,
            'response_report' => $this->whenLoaded('report', fn () => new ReportResource($this->report)),
            'destination' => $this->whenLoaded('destination', fn () => new EmergencyStationResource($this->destination)),
            'attended_by' => $this->whenLoaded('attendedBy', fn () => new UserResource($this->attendedBy)),
        ];
    }
}
