<?php

namespace Database\Seeders;

use App\Enums\StateReport;
use App\Models\Report;
use App\Models\ReportType;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Reportes de demostración (útiles para el mapa y las métricas). */
class SampleReportSeeder extends Seeder
{
    public function run(): void
    {
        if (Report::withTrashed()->count() > 0) {
            return;
        }

        $user = User::orderBy('created_at')->first();
        if (! $user) {
            $this->command?->warn('Sin usuarios: se omiten los reportes de demo');

            return;
        }

        $samples = [
            ['Accidente de tránsito en Av. Banzer y 2do anillo', -17.7833, -63.1812, 'Centro', true, 1],
            ['Robo agravado reportado por vecinos', -17.79, -63.195, 'Equipetrol', false, 2],
            ['Incendio en bodega comercial', -17.775, -63.17, 'Plan 3000', true, 3],
            ['Emergencia médica — persona inconsciente', -17.768, -63.205, 'Urbari', false, 4],
            ['Violencia familiar en zona residencial', -17.801, -63.188, 'La Guardia', true, 5],
        ];

        foreach ($samples as [$description, $lat, $lng, $zone, $verified, $typeId]) {
            $type = ReportType::find($typeId) ?? ReportType::first();

            Report::create([
                'description' => $description,
                'latitude' => $lat,
                'longitude' => $lng,
                'weight' => $type->base_weight,
                'zone' => $zone,
                'verified' => $verified,
                'expires_at' => now()->addDays(7),
                'status' => StateReport::Activo,
                'creator_id' => $user->id,
                'report_type_id' => $type->id,
            ]);
        }

        $this->command?->info(count($samples).' reportes de demo insertados');
    }
}
