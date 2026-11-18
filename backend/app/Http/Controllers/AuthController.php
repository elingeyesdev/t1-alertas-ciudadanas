<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\PublicRegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    /** POST /api/auth/register (público): el rol siempre es "usuario" (ciudadano). */
    public function register(PublicRegisterRequest $request)
    {
        $data = $request->validated();
        $data['roleId'] = Role::where('name', 'usuario')->value('id');

        return response()->json($this->auth->register($data), 201);
    }

    /** POST /api/auth/login (público) */
    public function login(LoginRequest $request)
    {
        $user = $this->auth->validateUser($request->input('phone'), $request->input('password'));

        return response()->json($this->auth->login($user), 201);
    }

    /** GET /api/auth/me */
    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    /** POST /api/auth/refresh (público) */
    public function refresh(Request $request)
    {
        return response()->json($this->auth->refreshToken($request->input('refresh_token')), 201);
    }

    /** POST /api/auth/logout */
    public function logout(Request $request)
    {
        return response()->json($this->auth->logout($request->user()), 201);
    }
}
