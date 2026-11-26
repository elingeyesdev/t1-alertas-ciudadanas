<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MissingCaseContributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author' => $this->author?->first_name,
            'message' => $this->message,
            'photo_count' => count($this->photos),
            'has_video' => $this->video_path !== null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
