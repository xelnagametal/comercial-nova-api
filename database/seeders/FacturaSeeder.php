<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Cliente;
use App\Models\DetalleFactura;
use App\Models\Factura;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FacturaSeeder extends Seeder
{
    /**
     * Emite facturas coherentes: detalles a partir de productos con stock,
     * totales recalculados, stock descontado y movimientos de venta registrados.
     */
    public function run(): void
    {
        $clientes = Cliente::query()->where('activo', true)->get();
        $vendedores = User::role(Rol::Vendedor->value)->get();

        for ($numero = 1; $numero <= 30; $numero++) {
            DB::transaction(function () use ($numero, $clientes, $vendedores): void {
                $factura = Factura::factory()
                    ->for($clientes->random())
                    ->for($vendedores->random(), 'vendedor')
                    ->create(['numero' => sprintf('001-001-%09d', $numero)]);

                $productos = Producto::query()
                    ->where('activo', true)
                    ->where('stock', '>=', 5)
                    ->inRandomOrder()
                    ->limit(fake()->numberBetween(1, 5))
                    ->get();

                foreach ($productos as $producto) {
                    $detalle = DetalleFactura::factory()
                        ->for($factura)
                        ->for($producto)
                        ->create([
                            'descuento' => fake()->boolean(20) ? round($producto->precio * 0.1, 2) : 0,
                        ]);

                    $producto->stock -= $detalle->cantidad;
                    $producto->version++;
                    $producto->save();

                    MovimientoInventario::factory()
                        ->for($producto)
                        ->for($factura->vendedor)
                        ->for($factura)
                        ->create([
                            'tipo' => 'venta',
                            'cantidad' => -$detalle->cantidad,
                            'stock_resultante' => $producto->stock,
                            'motivo' => null,
                            'created_at' => $factura->fecha_emision,
                        ]);
                }

                $this->recalcularTotales($factura);
            });
        }
    }

    /**
     * Actualiza los totales de la factura sumando sus detalles.
     */
    private function recalcularTotales(Factura $factura): void
    {
        $detalles = $factura->detalles()->get();

        $subtotalIva0 = $detalles->where('porcentaje_iva', 0)->sum('subtotal');
        $subtotalIva15 = $detalles->where('porcentaje_iva', '>', 0)->sum('subtotal');
        $iva = $detalles->sum('valor_iva');

        $factura->update([
            'subtotal_iva_0' => $subtotalIva0,
            'subtotal_iva_15' => $subtotalIva15,
            'descuento' => $detalles->sum('descuento'),
            'iva' => $iva,
            'total' => round($subtotalIva0 + $subtotalIva15 + $iva, 2),
        ]);
    }
}
