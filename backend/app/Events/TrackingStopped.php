<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Canal público "trackings", evento "tracking_stopped". */
class TrackingStopped implements ShouldBroadcastNow
{
    public function __construct(public string $userId) {}

    public function broadcastOn(): array
    {
        return [new Channel('trackings')];
    }

    public function broadcastAs(): string
    {
        return 'tracking_stopped';
    }

    public function broadcastWith(): array
    {
        return ['userId' => $this->userId];
    }
}
