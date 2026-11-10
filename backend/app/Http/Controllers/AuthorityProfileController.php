<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAuthorityProfileRequest;
use App\Services\AuthorityProfileService;

class AuthorityProfileController extends Controller
{
    public function __construct(private readonly AuthorityProfileService $profiles) {}

    /** PATCH /api/authority-profile/{userId} */
    public function update(UpdateAuthorityProfileRequest $request, string $id)
    {
        return $this->profiles->updateByUserId($id, $request->validated());
    }
}
