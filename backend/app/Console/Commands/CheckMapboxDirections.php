<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EmergencyRoutingService;
use RuntimeException;

class CheckMapboxDirections extends Command
{
    protected $signature = 'mapbox:check-directions';

    protected $description = 'Comprueba el acceso a Mapbox Directions con un recorrido de prueba';

    public function handle(EmergencyRoutingService $routing): int
    {
        $token = config('services.mapbox.directions_token');
        if (blank($token)) {
            $this->error('Configura MAPBOX_DIRECTIONS_TOKEN antes de comprobar Directions.');

            return self::FAILURE;
        }

        try {
            $routing->calculate(-63.181, -17.783, -63.155, -17.765);
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $this->info('Mapbox Directions devolvió una ruta con geometría, distancia y duración.');

        return self::SUCCESS;
    }
}
