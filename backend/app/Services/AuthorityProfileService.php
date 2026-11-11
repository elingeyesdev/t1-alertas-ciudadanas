<?php

namespace App\Services;

use App\Http\Resources\AuthorityProfileResource;
use App\Models\AuthorityProfile;
use App\Models\User;

class AuthorityProfileService
{
    public function create(User $user, array $data): AuthorityProfile
    {
        return $user->authorityProfile()->create($data);
    }

    public function updateByUserId(string $userId, array $data): AuthorityProfileResource
    {
        $user = User::with('authorityProfile')->find($userId);
        if (! $user) {
            abort(404, "El user con ID {$userId} no se encontro");
        }

        $profile = $user->authorityProfile;
        if (! $profile) {
            abort(404, "El perfil de autoridad para el usuario con ID {$userId} no se encontro");
        }

        $profile->fill($data)->save();

        return new AuthorityProfileResource($profile);
    }
}
