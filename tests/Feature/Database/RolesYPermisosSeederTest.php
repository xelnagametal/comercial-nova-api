<?php

use App\Models\Role;
use Database\Seeders\RolesYPermisosSeeder;
use Spatie\Permission\Models\Permission;

it('gives the administrador every permission and the vendedor only its own', function () {
    $this->seed(RolesYPermisosSeeder::class);

    expect(Role::findByName('administrador')->permissions->pluck('name')->sort()->values()->all())
        ->toBe(Permission::pluck('name')->sort()->values()->all())
        ->toHaveCount(21);
    expect(Role::findByName('vendedor')->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['categorias.ver', 'clientes.crear', 'clientes.editar', 'clientes.ver', 'productos.ver']);
});

it('keeps the vendedor permissions edited through the api when it runs again', function () {
    $this->seed(RolesYPermisosSeeder::class);
    Role::findByName('vendedor')->syncPermissions(['productos.ver']);
    Role::findByName('administrador')->syncPermissions([]);

    $this->seed(RolesYPermisosSeeder::class);

    expect(Role::findByName('vendedor')->permissions->pluck('name')->all())->toBe(['productos.ver']);
    expect(Role::findByName('administrador')->permissions)->toHaveCount(21);
});

it('can run twice without duplicating roles or permissions', function () {
    $this->seed(RolesYPermisosSeeder::class);
    $this->seed(RolesYPermisosSeeder::class);

    expect(Role::count())->toBe(2);
    expect(Permission::count())->toBe(21);
});
