<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envía notificaciones push con Firebase Cloud Messaging (API HTTP v1)
 * usando el JSON de la cuenta de servicio (reemplaza a firebase-admin).
 */
class FcmService
{
    public function sendAmbulanceAlert(string $token, string $alertId, int $expiresAt): string
    {
        try {
            $credentials = $this->credentials();
            $accessToken = $this->accessToken($credentials);
        } catch (Throwable) {
            return 'retry';
        }

        $ttl = $expiresAt - time();
        if ($ttl <= 0) {
            return 'expired';
        }

        try {
            $response = Http::withToken($accessToken)->connectTimeout(2)->timeout(5)
                ->post("https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => 'Ambulancia próxima',
                            'body' => 'Una ambulancia se aproxima a tu zona. Facilita el paso de forma segura.',
                        ],
                        'data' => ['type' => 'ambulance_approaching', 'alert_id' => $alertId],
                        'android' => [
                            'priority' => 'high',
                            'ttl' => "{$ttl}s",
                            'notification' => [
                                'channel_id' => 'ambulance_alerts_sound_v3',
                                'sound' => 'ambulance_proximity_siren',
                                'tag' => $alertId,
                            ],
                        ],
                        'apns' => [
                            'headers' => ['apns-expiration' => (string) $expiresAt, 'apns-collapse-id' => $alertId],
                            'payload' => ['aps' => ['sound' => 'default']],
                        ],
                    ],
                ]);
        } catch (Throwable) {
            return 'uncertain';
        }

        return match (true) {
            $response->successful() => 'sent',
            $response->serverError() => 'uncertain',
            default => 'rejected',
        };
    }

    public function enabled(): bool
    {
        return is_file((string) config('alertas.firebase.credentials'));
    }

    /**
     * Equivale a sendEachForMulticast: envía a varios tokens.
     *
     * @param  string[]  $tokens
     * @return array{successCount: int, failureCount: int}
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        $result = ['successCount' => 0, 'failureCount' => 0];

        if (! $tokens) {
            return $result;
        }

        if (! $this->enabled()) {
            Log::warning('FCM deshabilitado: no se encontró '.config('alertas.firebase.credentials'));
            $result['failureCount'] = count($tokens);

            return $result;
        }

        $credentials = $this->credentials();
        $accessToken = $this->accessToken($credentials);
        $url = "https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send";
        // FCM exige que todos los valores de "data" sean strings
        $data = array_map(fn ($v) => (string) $v, $data);

        foreach ($tokens as $token) {
            try {
                $response = Http::withToken($accessToken)->timeout(15)->post($url, [
                    'message' => [
                        'token' => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data' => (object) $data,
                    ],
                ]);
                $response->successful() ? $result['successCount']++ : $result['failureCount']++;
            } catch (Throwable $e) {
                $result['failureCount']++;
                Log::error('Error enviando push FCM', ['error' => $e->getMessage()]);
            }
        }

        Log::info("Notificaciones enviadas. Exitos: {$result['successCount']}, Fallos: {$result['failureCount']}");

        return $result;
    }

    private function credentials(): array
    {
        return json_decode((string) file_get_contents(config('alertas.firebase.credentials')), true);
    }

    /** Obtiene (y cachea ~55 min) un access token OAuth2 de Google. */
    private function accessToken(array $credentials): string
    {
        return Cache::remember('fcm_access_token', 3300, function () use ($credentials) {
            $now = time();
            $assertion = JWT::encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], $credentials['private_key'], 'RS256');

            return Http::asForm()->connectTimeout(2)->timeout(5)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ])->throw()->json('access_token');
        });
    }
}
