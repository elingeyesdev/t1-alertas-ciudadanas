<?php

namespace App\Support;

use DateTimeInterface;

class Dt
{
    /** Formato ISO 8601 igual al que devolvía NestJS (2026-01-01T12:00:00.000Z). */
    public static function iso(?DateTimeInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return \Carbon\CarbonImmutable::instance($date)->utc()->format('Y-m-d\TH:i:s.v\Z');
    }
}
