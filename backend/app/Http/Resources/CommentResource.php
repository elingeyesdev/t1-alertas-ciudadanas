<?php

namespace App\Http\Resources;

use App\Support\Dt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Equivale a CommentResponse */
class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $parent = $this->relationLoaded('parentComment') ? $this->parentComment : null;
        $creator = $this->creator;

        $data = [
            'id' => $this->id,
            'text' => $this->text,
            'created_at' => Dt::iso($this->created_at),
            'parent_comment' => $parent ? [
                'id' => $parent->id,
                'text' => $parent->text,
                'created_at' => Dt::iso($parent->created_at),
            ] : null,
            'creator' => [
                'id' => $creator?->id,
                'first_name' => $creator?->first_name,
                'last_name' => $creator?->last_name,
                'phone' => $creator?->phone,
            ],
        ];

        if (isset($this->responses_count)) {
            $data['replies_count'] = (int) $this->responses_count;
        }

        return $data;
    }
}
