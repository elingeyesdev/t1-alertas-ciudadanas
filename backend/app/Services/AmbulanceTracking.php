<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class AmbulanceTracking
{
    public function cache(): Repository
    {
        return Cache::store(config('alertas.ambulance.cache_store'));
    }

    public function key(int $dispatchId, string $field): string
    {
        return "ambulance:{$dispatchId}:{$field}";
    }
}
