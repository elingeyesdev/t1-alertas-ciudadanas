<?php

namespace App\Http\Resources;

use App\Support\Dt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Equivale a ReportCoinicdenceResponse */
class ReportCoincidenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description ?? '',
            'coordinates' => [$this->longitude, $this->latitude],
            'weight' => $this->weight ?? 0,
            'verified' => (bool) $this->verified,
            'created_at' => Dt::iso($this->created_at),
            'images' => ImageResource::collection($this->whenLoaded('images', fn () => $this->images, collect())),
            'zone' => $this->zone ?? 'Sin zona',
        ];
    }
}
