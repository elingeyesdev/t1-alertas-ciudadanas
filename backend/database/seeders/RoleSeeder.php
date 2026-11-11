<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        if (Role::count() > 0) {
            return;
        }

        Role::insert([
            ['id' => 1, 'name' => 'usuario'],
            ['id' => 2, 'name' => 'autoridad'],
            ['id' => 3, 'name' => 'admin'],
        ]);

        // Como insertamos ids a mano, sincronizamos la secuencia de PostgreSQL
        DB::statement("SELECT setval(pg_get_serial_sequence('roles', 'id'), (SELECT MAX(id) FROM roles))");

        $this->command?->info('Roles insertados: usuario (1), autoridad (2), admin (3)');
    }
}
