<?php

namespace Database\Factories;

use App\Models\Factura;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * La cantidad lleva signo: positiva para entradas, negativa para ventas.
 *
 * @extends Factory<MovimientoInventario>
 */
class MovimientoInventarioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory(),
            'user_id' => User::factory(),
            'factura_id' => null,
            'tipo' => 'entrada',
            'cantidad' => fake()->numberBetween(1, 50),
            'stock_resultante' => fake()->numberBetween(50, 200),
            'motivo' => 'Compra a proveedor',
        ];
    }

    /**
     * Salida de inventario por una venta facturada.
     */
    public function venta(): static
    {
        return $this->state(fn (array $attributes) => [
            'factura_id' => Factura::factory(),
            'tipo' => 'venta',
            'cantidad' => -fake()->numberBetween(1, 5),
            'motivo' => null,
        ]);
    }

    /**
     * Ajuste manual de inventario (conteo físico, merma, etc.).
     */
    public function ajuste(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => 'ajuste',
            'cantidad' => fake()->numberBetween(-10, 10) ?: 1,
            'motivo' => 'Ajuste por conteo físico',
        ]);
    }
}
