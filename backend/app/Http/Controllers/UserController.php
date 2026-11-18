<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateAuthorityUserRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    /** POST /api/users (admin): crea un usuario con el rol indicado. */
    public function store(RegisterUserRequest $request)
    {
        return (new UserResource($this->users->create($request->validated())))
            ->response()->setStatusCode(201);
    }

    public function createAuthority(CreateAuthorityUserRequest $request)
    {
        // Este endpoint solo crea autoridades: el rol no se toma del cliente.
        $data = $request->validated();
        $data['roleId'] = Role::where('name', 'autoridad')->value('id');

        return $this->users->createAuthUser($data)->response()->setStatusCode(201);
    }

    public function index()
    {
        return $this->users->findAll();
    }

    public function show(Request $request, string $id)
    {
        $this->ensureSelfOrAdmin($request, $id);

        return $this->users->findOne($id);
    }

    public function update(UpdateUserRequest $request, string $id)
    {
        $this->ensureSelfOrAdmin($request, $id);

        return $this->users->update($id, $request->validated());
    }

    public function updateFcmToken(Request $request, string $id)
    {
        $this->ensureSelf($request, $id);
        $request->validate(['fcm_token' => ['nullable', 'string']]);

        return $this->users->updateFcmToken($id, $request->input('fcm_token'));
    }

    public function updateLocation(UpdateLocationRequest $request, string $id)
    {
        $this->ensureSelf($request, $id);

        return $this->users->updateLocation($id, (float) $request->input('latitude'), (float) $request->input('longitude'));
    }

    public function updatePassword(UpdatePasswordRequest $request, string $id)
    {
        $this->ensureSelf($request, $id);

        return $this->users->updatePassword($id, $request->input('current_password'), $request->input('new_password'));
    }

    public function destroy(string $id)
    {
        return $this->users->remove($id);
    }
}
