<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** php artisan db:seed  (o php artisan migrate --seed) */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ReportTypeSeeder::class,
        ]);

        if (app()->environment('local', 'testing')) {
            $this->call(CitizenUserSeeder::class);
        }

        $this->call([
            AdminUserSeeder::class,
            EmergencyStationSeeder::class,
            SampleReportSeeder::class,
        ]);
    }
}
