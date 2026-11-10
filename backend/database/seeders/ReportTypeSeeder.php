<?php

namespace Database\Seeders;

use App\Models\ReportType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReportTypeSeeder extends Seeder
{
    public function run(): void
    {
        if (ReportType::count() > 0) {
            return;
        }

        ReportType::insert([
            ['id' => 1, 'name' => 'Accidente de tránsito', 'base_weight' => 9],
            ['id' => 2, 'name' => 'Robo agravado', 'base_weight' => 9],
            ['id' => 3, 'name' => 'Incendio', 'base_weight' => 10],
            ['id' => 4, 'name' => 'Emergencia médica', 'base_weight' => 9],
            ['id' => 5, 'name' => 'Violencia familiar', 'base_weight' => 8],
            ['id' => 6, 'name' => 'Robo menor', 'base_weight' => 4],
            ['id' => 7, 'name' => 'Disturbio', 'base_weight' => 5],
        ]);

        DB::statement("SELECT setval(pg_get_serial_sequence('report_types', 'id'), (SELECT MAX(id) FROM report_types))");

        $this->command?->info('Tipos de reporte insertados (7)');
    }
}
