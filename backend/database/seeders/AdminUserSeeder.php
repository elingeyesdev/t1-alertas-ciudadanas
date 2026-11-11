<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Crea un administrador inicial para poder entrar al sistema.
 * ¡Cambia la contraseña después del primer inicio de sesión!
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $phone = env('ADMIN_PHONE', '70000000');

        if (User::withTrashed()->where('phone', $phone)->exists()) {
            return;
        }

        User::create([
            'first_name' => 'admin',
            'last_name' => 'sistema',
            'phone' => $phone,
            'password' => env('ADMIN_PASSWORD', 'admin123'),
            'role_id' => 3,
        ]);

        $this->command?->info("Administrador creado: teléfono {$phone}");
    }
}
