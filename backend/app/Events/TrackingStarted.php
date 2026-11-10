<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Canal público "trackings", evento "tracking_started" (antes TrackingsGateway). */
class TrackingStarted implements ShouldBroadcastNow
{
    public function __construct(public array $tracking) {}

    public function broadcastOn(): array
    {
        return [new Channel('trackings')];
    }

    public function broadcastAs(): string
    {
        return 'tracking_started';
    }

    public function broadcastWith(): array
    {
        return $this->tracking;
    }
}
