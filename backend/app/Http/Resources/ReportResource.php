<?php

namespace App\Http\Resources;

use App\Support\Dt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Equivale a ReportResponse.FromReportToResponse */
class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'creator' => $this->creator_id ?? '',
            'type' => $this->whenLoaded('type', fn () => new ReportTypeResource($this->type)),
            'description' => $this->description ?? '',
            'status' => $this->status?->value,
            // [longitud, latitud] — mismo orden que GeoJSON/PostGIS
            'coordinates' => [$this->longitude, $this->latitude],
            'weight' => $this->weight ?? 0,
            'verified' => (bool) $this->verified,
            'created_at' => Dt::iso($this->created_at),
            'updated_at' => Dt::iso($this->updated_at ?? $this->created_at),
            'expires_at' => Dt::iso($this->expires_at),
            'zone' => $this->zone ?? 'Sin zona',
            'images' => ImageResource::collection($this->whenLoaded('images', fn () => $this->images, collect())),
        ];

        if ($this->relationLoaded('dispatches')) {
            $data['dispatches'] = $this->dispatches->map(fn ($d) => [
                'attended_by' => $d->attendedBy ? new UserResource($d->attendedBy) : null,
                'recorded_at' => Dt::iso($d->recorded_at),
                'state' => $d->state?->value,
            ])->all();
        }

        return $data;
    }
}
