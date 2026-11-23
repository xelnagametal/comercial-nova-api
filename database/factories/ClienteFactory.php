<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo_identificacion' => 'CEDULA',
            'identificacion' => fake()->unique()->numerify('##########'),
            'nombre' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'telefono' => fake()->numerify('09########'),
            'direccion' => fake()->streetAddress(),
            'activo' => true,
        ];
    }

    /**
     * Cliente identificado con RUC (13 dígitos terminados en 001).
     */
    public function conRuc(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_identificacion' => 'RUC',
            'identificacion' => fake()->unique()->numerify('##########001'),
            'nombre' => fake()->company(),
        ]);
    }

    /**
     * Cliente extranjero identificado con pasaporte.
     */
    public function conPasaporte(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_identificacion' => 'PASAPORTE',
            'identificacion' => fake()->unique()->bothify('??#######'),
        ]);
    }

    /**
     * Cliente desactivado.
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}
