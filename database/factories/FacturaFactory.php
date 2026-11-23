<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * La factura se crea con totales en cero; deben recalcularse a partir de sus detalles.
 *
 * @extends Factory<Factura>
 */
class FacturaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'user_id' => User::factory(),
            'numero' => sprintf('001-001-%09d', fake()->unique()->numberBetween(1, 999999999)),
            'fecha_emision' => fake()->dateTimeBetween('-3 months'),
            'subtotal_iva_0' => 0,
            'subtotal_iva_15' => 0,
            'descuento' => 0,
            'iva' => 0,
            'total' => 0,
            'forma_pago' => fake()->randomElement(['efectivo', 'tarjeta', 'transferencia']),
            'estado' => 'emitida',
            'observacion' => null,
        ];
    }

    /**
     * Factura anulada.
     */
    public function anulada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'anulada',
            'observacion' => 'Anulada por error en la emisión',
        ]);
    }
}
