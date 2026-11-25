<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** HU-32: un aviso preventivo nuevo; el mapa del panel lo dibuja al momento. */
class PublicAlertPublished implements ShouldBroadcastNow
{
    public function __construct(public array $alert) {}

    public function broadcastOn(): array
    {
        return [new Channel('public-alerts')];
    }

    public function broadcastAs(): string
    {
        return 'publicAlert';
    }

    public function broadcastWith(): array
    {
        return $this->alert;
    }
}
