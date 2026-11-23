<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Crea el administrador (admin@example.com / password) y 20 vendedores.
 */
class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()
            ->create([
                'name' => 'Administrador',
                'email' => 'admin@example.com',
            ])
            ->assignRole(Rol::Administrador->value);

        User::factory()
            ->count(20)
            ->create()
            ->each(fn (User $vendedor) => $vendedor->assignRole(Rol::Vendedor->value));
    }
}
