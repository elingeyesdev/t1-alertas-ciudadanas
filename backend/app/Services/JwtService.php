<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use stdClass;

/**
 * Reemplaza a @nestjs/jwt + passport-jwt.
 * Firma tokens HS256 con JWT_SECRET. Se agrega el claim "typ" para
 * distinguir access tokens de refresh tokens.
 */
class JwtService
{
    public const ACCESS = 'access';

    public const REFRESH = 'refresh';

    public function issue(User $user, string $type): string
    {
        $ttl = $type === self::REFRESH
            ? config('alertas.jwt.refresh_ttl')
            : config('alertas.jwt.access_ttl');

        $now = time();
        $payload = [
            'id' => $user->id,
            'phone' => $user->phone,
            'typ' => $type,
            'iat' => $now,
            'exp' => $now + self::parseDuration((string) $ttl),
            'jti' => bin2hex(random_bytes(8)),
        ];

        return JWT::encode($payload, $this->secret(), 'HS256');
    }

    /** Decodifica y valida firma/expiración. Lanza excepción si es inválido. */
    public function decode(string $token): stdClass
    {
        return JWT::decode($token, new Key($this->secret(), 'HS256'));
    }

    /** Convierte "1h", "30m", "72h", "7d", "45s" o "3600" a segundos. */
    public static function parseDuration(string $value): int
    {
        if (preg_match('/^\s*(\d+)\s*([smhd]?)\s*$/i', $value, $m) !== 1) {
            return 3600;
        }

        return (int) $m[1] * match (strtolower($m[2])) {
            'm' => 60,
            'h' => 3600,
            'd' => 86400,
            default => 1,
        };
    }

    private function secret(): string
    {
        $secret = config('alertas.jwt.secret');
        if (! $secret) {
            throw new RuntimeException('JWT_SECRET no está configurado en el archivo .env');
        }
        if (strlen($secret) < 32) {
            throw new RuntimeException('JWT_SECRET debe tener al menos 32 caracteres (requisito de HS256)');
        }

        return $secret;
    }
}
