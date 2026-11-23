<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'categoria_id' => Categoria::factory(),
            'codigo' => fake()->unique()->bothify('PRD-#####'),
            'nombre' => ucfirst(fake()->words(3, true)),
            'precio' => fake()->randomFloat(2, 0.5, 250),
            'porcentaje_iva' => 15,
            'stock' => fake()->numberBetween(10, 200),
            'version' => 1,
            'activo' => true,
        ];
    }

    /**
     * Producto con tarifa 0% de IVA.
     */
    public function sinIva(): static
    {
        return $this->state(fn (array $attributes) => [
            'porcentaje_iva' => 0,
        ]);
    }

    /**
     * Producto sin unidades disponibles.
     */
    public function sinStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }

    /**
     * Producto desactivado.
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}
