<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthorityProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ci' => $this->ci,
            'gmail' => $this->gmail,
            'profile_type' => $this->profile_type?->value,
        ];
    }
}
