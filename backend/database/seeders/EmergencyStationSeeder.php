<?php

namespace Database\Seeders;

use App\Models\EmergencyStation;
use Illuminate\Database\Seeder;

/** Estaciones de emergencia en Santa Cruz (policía, bomberos, hospitales). */
class EmergencyStationSeeder extends Seeder
{
    public function run(): void
    {
        if (EmergencyStation::withTrashed()->count() > 0) {
            return;
        }

        $stations = [
            ['Comando Departamental Policía SC', 'policia', -17.7833, -63.1821],
            ['UTOP Equipetrol', 'policia', -17.7565, -63.1968],
            ['UTOP Sur', 'policia', -17.81, -63.175],
            ['Cuartel General Bomberos SC', 'bombero', -17.7895, -63.1755],
            ['Compañía Bomberos Plan 3000', 'bombero', -17.772, -63.168],
            ['Hospital Japonés', 'hospital', -17.7598, -63.1902],
            ['Hospital Obrero', 'hospital', -17.7836, -63.1765],
            ['Clínica Las Américas', 'hospital', -17.783, -63.181],
        ];

        foreach ($stations as [$name, $type, $lat, $lng]) {
            EmergencyStation::create([
                'name' => $name,
                'installation_type' => $type,
                'latitude' => $lat,
                'longitude' => $lng,
            ]);
        }

        $this->command?->info(count($stations).' estaciones de emergencia insertadas');
    }
}
