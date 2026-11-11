<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Equivale a EmergencyStationResponse */
class EmergencyStationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'installation_type' => $this->installation_type?->value,
            'coordinates' => [$this->longitude, $this->latitude],
        ];
    }
}
