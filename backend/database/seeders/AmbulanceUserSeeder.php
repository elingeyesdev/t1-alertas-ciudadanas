<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class AmbulanceUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new LogicException('La cuenta de ambulancia de prueba solo se puede crear en desarrollo.');
        }

        $this->call(RoleSeeder::class);
        $roleId = Role::query()->where('name', 'autoridad')->firstOrFail()->id;

        DB::transaction(function () use ($roleId) {
            $user = User::withTrashed()->firstOrCreate(
                ['phone' => '70000001'],
                ['first_name' => 'Ambulancia', 'last_name' => 'Demo', 'password' => 'ambulancia123', 'role_id' => $roleId],
            );

            if (! $user->wasRecentlyCreated) {
                if ($user->trashed() || $user->role_id !== $roleId
                    || $user->authorityProfile?->profile_type?->value !== 'paramedico') {
                    throw new LogicException('El teléfono de prueba está ocupado por otra cuenta.');
                }

                return;
            }

            $user->authorityProfile()->create([
                'ci' => '90000001',
                'gmail' => 'ambulancia.demo@example.test',
                'profile_type' => 'paramedico',
            ]);
        });

        $this->command?->info('Cuenta de ambulancia de prueba disponible en el teléfono 70000001.');
    }
}
