<?php

namespace Database\Factories;

use App\Models\Alerta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alerta>
 */
class AlertaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo' => fake()->randomElement(array_keys(Alerta::TIPOS)),
            'descripcion' => fake()->sentence(6),
            'ubicacion' => fake()->streetAddress(),
            'estado' => fake()->randomElement(array_keys(Alerta::ESTADOS)),
            'fecha' => fake()->dateTimeBetween('-10 days', 'now'),
        ];
    }
}
