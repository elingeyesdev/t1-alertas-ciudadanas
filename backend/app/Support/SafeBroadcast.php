<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

class SafeBroadcast
{
    /** Emite un evento en tiempo real sin romper la petición si Reverb está apagado. */
    public static function send(object $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $e) {
            Log::warning('No se pudo emitir el evento en tiempo real: '.$e->getMessage());
        }
    }
}
