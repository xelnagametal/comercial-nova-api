<?php

namespace Database\Factories;

use App\Models\DetalleFactura;
use App\Models\Factura;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Copia código, descripción, precio e IVA del producto y calcula los importes de la línea.
 *
 * @extends Factory<DetalleFactura>
 */
class DetalleFacturaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'factura_id' => Factura::factory(),
            'producto_id' => Producto::factory(),
            'codigo_producto' => fn (array $attributes) => Producto::find($attributes['producto_id'])->codigo,
            'descripcion' => fn (array $attributes) => Producto::find($attributes['producto_id'])->nombre,
            'cantidad' => fake()->numberBetween(1, 5),
            'precio_unitario' => fn (array $attributes) => Producto::find($attributes['producto_id'])->precio,
            'descuento' => 0,
            'subtotal' => fn (array $attributes) => round($attributes['cantidad'] * $attributes['precio_unitario'] - $attributes['descuento'], 2),
            'porcentaje_iva' => fn (array $attributes) => Producto::find($attributes['producto_id'])->porcentaje_iva,
            'valor_iva' => fn (array $attributes) => round($attributes['subtotal'] * $attributes['porcentaje_iva'] / 100, 2),
            'total' => fn (array $attributes) => round($attributes['subtotal'] + $attributes['valor_iva'], 2),
        ];
    }
}
