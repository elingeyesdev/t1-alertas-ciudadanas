<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MissingCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'status' => $this->status,
            'name' => $this->name,
            'place' => $this->place,
            'missing_on' => $this->missing_on->toDateString(),
            'description' => $this->description,
            'reference_phone' => $this->when($this->status === 'published', $this->reference_phone),
            'photo_count' => count($this->photos),
            'evidence' => $this->whenLoaded('mediaContributions', function () {
                $evidence = [];
                foreach (array_keys($this->photos) as $index) {
                    $evidence[] = ['type' => 'photo', 'index' => $index];
                }
                foreach ($this->mediaContributions as $item) {
                    if ($item->video_path !== null) {
                        $evidence[] = ['type' => 'video', 'contribution_id' => $item->id];
                    }
                    foreach (array_keys($item->photos) as $index) {
                        $evidence[] = ['type' => 'photo', 'index' => $index, 'contribution_id' => $item->id];
                    }
                }

                return $evidence;
            }),
        ];
    }
}
