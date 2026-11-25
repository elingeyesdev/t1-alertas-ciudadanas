<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** HU-32: el administrador finalizó o eliminó un aviso; el mapa lo quita. */
class PublicAlertEnded implements ShouldBroadcastNow
{
    public function __construct(public int $id) {}

    public function broadcastOn(): array
    {
        return [new Channel('public-alerts')];
    }

    public function broadcastAs(): string
    {
        return 'publicAlertEnded';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->id];
    }
}
