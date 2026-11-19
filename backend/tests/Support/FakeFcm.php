<?php

namespace Tests\Support;

use App\Services\FcmService;

/** FCM de mentira para los tests: guarda los push en vez de enviarlos. */
class FakeFcm extends FcmService
{
    /** @var array<int, array{tokens: array, title: string, body: string, data: array}> */
    public array $sent = [];

    public function enabled(): bool
    {
        return true;
    }

    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        $this->sent[] = compact('tokens', 'title', 'body', 'data');

        return ['successCount' => count($tokens), 'failureCount' => 0];
    }

    /** Push enviados de un tipo (data.type). */
    public function ofType(string $type): array
    {
        return array_values(array_filter($this->sent, fn ($m) => ($m['data']['type'] ?? null) === $type));
    }
}
