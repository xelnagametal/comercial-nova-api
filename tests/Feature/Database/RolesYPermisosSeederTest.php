<?php

use Database\Seeders\RolesYPermisosSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('gives the administrador every permission and the vendedor only its own', function () {
    $this->seed(RolesYPermisosSeeder::class);

    expect(Role::findByName('administrador')->permissions->pluck('name')->sort()->values()->all())
        ->toBe(Permission::pluck('name')->sort()->values()->all())
        ->toHaveCount(16);
    expect(Role::findByName('vendedor')->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['categorias.ver', 'clientes.crear', 'clientes.editar', 'clientes.ver', 'productos.ver']);
});

it('can run twice without duplicating roles or permissions', function () {
    $this->seed(RolesYPermisosSeeder::class);
    $this->seed(RolesYPermisosSeeder::class);

    expect(Role::count())->toBe(2);
    expect(Permission::count())->toBe(16);
});
