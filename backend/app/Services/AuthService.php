<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Throwable;

class AuthService
{
    public function __construct(
        private readonly UserService $users,
        private readonly JwtService $jwt,
    ) {}

    /** Equivale a LocalStrategy + AuthService.validateUser */
    public function validateUser(string $phone, string $password): User
    {
        $user = $this->users->findByPhone($phone);
        if (! $user) {
            abort(404, 'usuario no encontrado');
        }
        if (! Hash::check($password, $user->password)) {
            abort(400, 'Contrasena Incorrecta');
        }

        return $user;
    }

    public function login(User $user): array
    {
        $refreshToken = $this->jwt->issue($user, JwtService::REFRESH);
        $accessToken = $this->jwt->issue($user, JwtService::ACCESS);

        // Se guarda solo el hash SHA-256 del refresh token
        $user->forceFill(['refresh_token' => hash('sha256', $refreshToken)])->save();

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'user' => new UserResource($user->load(['role', 'authorityProfile'])),
        ];
    }

    public function register(array $data): array
    {
        return $this->login($this->users->create($data));
    }

    public function refreshToken(?string $token): array
    {
        if (! $token) {
            abort(400, 'Token invalido');
        }

        try {
            $payload = $this->jwt->decode($token);
        } catch (Throwable) {
            abort(401, 'Token invalido o expirado');
        }

        if (($payload->typ ?? null) !== JwtService::REFRESH) {
            abort(400, 'Token invalido');
        }

        $user = User::find($payload->id ?? null);
        if (! $user) {
            abort(404, 'Usuario no encontrado');
        }

        if (! $user->refresh_token || ! hash_equals($user->refresh_token, hash('sha256', $token))) {
            abort(400, 'Token invalido');
        }

        return ['access_token' => $this->jwt->issue($user, JwtService::ACCESS)];
    }

    public function logout(User $user): array
    {
        $user->forceFill(['refresh_token' => null])->save();

        return ['message' => 'Logout exitoso'];
    }
}
