<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $nombres = ['Abarrotes', 'Bebidas', 'Lácteos', 'Limpieza', 'Cuidado personal', 'Snacks'];

        foreach ($nombres as $nombre) {
            Categoria::factory()->create(['nombre' => $nombre]);
        }
    }
}
