<?php

use App\Enums\Rol;

it('lists every permission grouped by resource', function () {
    actingAsRol(Rol::Administrador);

    $response = $this->getJson('/api/permisos');

    $response->assertOk()
        ->assertJsonCount(6, 'data')
        ->assertJsonPath('data.0.recurso', 'categorias')
        ->assertJsonPath('data.0.permisos.0.name', 'categorias.crear')
        ->assertJsonPath('data.0.permisos.0.accion', 'crear')
        ->assertJsonCount(4, 'data.0.permisos')
        ->assertJsonPath('data.2.recurso', 'permisos')
        ->assertJsonCount(1, 'data.2.permisos');
});

it('forbids a vendedor from listing permissions', function () {
    actingAsRol(Rol::Vendedor);

    $this->getJson('/api/permisos')->assertForbidden();
});

it('returns 401 without a token', function () {
    $this->getJson('/api/permisos')->assertUnauthorized();
});
