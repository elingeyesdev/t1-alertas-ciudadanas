<?php

namespace App\Providers;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JwtService::class);
    }

    public function boot(): void
    {
        // Las respuestas JSON NO se envuelven en { "data": ... }
        // para mantener el mismo formato que el backend NestJS.
        JsonResource::withoutWrapping();

        // Guard "jwt": lee el header Authorization: Bearer <token>
        // (equivale a JwtStrategy de passport-jwt).
        Auth::viaRequest('jwt', function (Request $request) {
            $token = $request->bearerToken();
            if (! $token) {
                return null;
            }

            try {
                $payload = app(JwtService::class)->decode($token);
            } catch (Throwable) {
                return null;
            }

            // Un refresh token no puede usarse como access token
            if (($payload->typ ?? JwtService::ACCESS) !== JwtService::ACCESS) {
                return null;
            }

            return User::with(['role', 'authorityProfile'])->find($payload->id ?? null);
        });
    }
}
