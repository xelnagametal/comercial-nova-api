<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductoSeeder extends Seeder
{
    /**
     * Crea productos por categoría y registra su inventario inicial como movimiento de entrada.
     */
    public function run(): void
    {
        $responsable = User::query()->firstOrFail();

        foreach (Categoria::all() as $categoria) {
            $productos = Producto::factory()
                ->count(4)
                ->for($categoria)
                ->create()
                ->concat(Producto::factory()->sinIva()->count(2)->for($categoria)->create());

            foreach ($productos as $producto) {
                MovimientoInventario::factory()
                    ->for($producto)
                    ->for($responsable)
                    ->create([
                        'tipo' => 'entrada',
                        'cantidad' => $producto->stock,
                        'stock_resultante' => $producto->stock,
                        'motivo' => 'Inventario inicial',
                    ]);
            }
        }
    }
}
