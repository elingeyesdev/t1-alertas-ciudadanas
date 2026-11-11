<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Equivale a UserResponse.FromUserToResponse */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['role', 'authorityProfile']);
        $profile = $this->authorityProfile;

        return [
            'id' => $this->id,
            'role' => $this->role ? ['id' => $this->role->id, 'name' => $this->role->name] : null,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'authority_profile' => $profile ? [
                'ci' => $profile->ci,
                'gmail' => $profile->gmail,
                'profile_type' => $profile->profile_type?->value,
            ] : null,
        ];
    }
}
