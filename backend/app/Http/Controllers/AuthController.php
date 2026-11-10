<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    /** POST /api/auth/register (público) */
    public function register(RegisterUserRequest $request)
    {
        return response()->json($this->auth->register($request->validated()), 201);
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
