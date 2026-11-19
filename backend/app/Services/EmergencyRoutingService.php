<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EmergencyRoutingService
{
    public function calculate(float $longitude, float $latitude, float $destinationLongitude, float $destinationLatitude): array
    {
        $token = config('services.mapbox.directions_token');
        $profile = config('services.mapbox.directions_profile');
        if (blank($token) || ! in_array($profile, ['mapbox/driving', 'mapbox/driving-traffic'], true)) {
            throw new RuntimeException('Configura el token y un perfil de conducción de Mapbox Directions.');
        }

        $coordinates = "{$longitude},{$latitude};{$destinationLongitude},{$destinationLatitude}";
        try {
            $response = Http::acceptJson()->connectTimeout(3)->timeout(8)
                ->retry(2, 250, fn ($error) => $error instanceof ConnectionException
                    || ($error instanceof RequestException && $error->response->serverError()), throw: false)
                ->get("https://api.mapbox.com/directions/v5/{$profile}/{$coordinates}", [
                    'access_token' => $token,
                    'geometries' => 'geojson',
                    'overview' => 'full',
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('No se pudo conectar con Mapbox Directions.');
        }

        $route = $response->json('routes.0');
        if (! $response->successful() || $response->json('code') !== 'Ok'
            || data_get($route, 'geometry.type') !== 'LineString'
            || ! is_array(data_get($route, 'geometry.coordinates'))
            || count($route['geometry']['coordinates']) < 2
            || ! is_numeric($route['distance'] ?? null)
            || ! is_numeric($route['duration'] ?? null)) {
            throw new RuntimeException("Directions no devolvió una ruta utilizable (HTTP {$response->status()}).");
        }

        return $route;
    }
}
