<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

class CitizenUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('Se omiten las cuentas ciudadanas de prueba fuera de desarrollo.');

            return;
        }

        $roleId = Role::query()->where('name', 'usuario')->firstOrFail()->id;
        $accounts = [
            ['first_name' => 'Carlos', 'last_name' => 'Vecino', 'phone' => '70000002', 'password' => 'vecino123'],
            ['first_name' => 'Ana', 'last_name' => 'Vecina', 'phone' => '70000003', 'password' => 'vecina123'],
        ];

        foreach ($accounts as $account) {
            $user = User::withTrashed()->where('phone', $account['phone'])->first();

            if ($user) {
                if ($user->trashed() || $user->role_id !== $roleId) {
                    throw new LogicException("El teléfono de prueba {$account['phone']} está ocupado por otra cuenta.");
                }

                continue;
            }

            User::create([...$account, 'role_id' => $roleId]);
        }

        $this->command?->info('Cuentas ciudadanas de prueba disponibles: 70000002 y 70000003.');
    }
}
