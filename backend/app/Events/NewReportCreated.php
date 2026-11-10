<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Reemplaza a ReportsGateway (socket.io, namespace "reports", evento "newReport").
 * Se emite por el canal público "reports" con el nombre "newReport".
 */
class NewReportCreated implements ShouldBroadcastNow
{
    public function __construct(public array $report) {}

    public function broadcastOn(): array
    {
        return [new Channel('reports')];
    }

    public function broadcastAs(): string
    {
        return 'newReport';
    }

    public function broadcastWith(): array
    {
        return $this->report;
    }
}
