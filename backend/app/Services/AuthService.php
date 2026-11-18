<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Throwable;

class AuthService
{
    public const INVALID_CREDENTIALS = 'Teléfono o contraseña incorrectos';

    /** Hash bcrypt de una contraseña que nadie usa (solo para igualar tiempos). */
    private const DUMMY_HASH = '$2y$12$BhmFTv8XTcqK3VCACdIAoOw3obwMpk8crKU7QwF9ByvRNkzwdKs3C';

    public function __construct(
        private readonly UserService $users,
        private readonly JwtService $jwt,
    ) {}

    /** Equivale a LocalStrategy + AuthService.validateUser */
    public function validateUser(string $phone, string $password): User
    {
        $user = $this->users->findByPhone($phone);

        // Se compara la contraseña aunque el teléfono no exista (con un hash de
        // relleno) para que el tiempo de respuesta no revele qué teléfonos están
        // registrados, y el mensaje es el mismo en los dos casos.
        $valid = Hash::check($password, $user?->password ?? self::DUMMY_HASH);
        if (! $user || ! $valid) {
            abort(400, self::INVALID_CREDENTIALS);
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
