<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => (string) fake()->unique()->numberBetween(60000000, 79999999),
            'password' => 'secret123',
            'role_id' => 1,
        ];
    }

    public function role(int $roleId): static
    {
        return $this->state(fn () => ['role_id' => $roleId]);
    }
}
