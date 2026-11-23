<?php

use App\Enums\Rol;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Payload válido para crear o actualizar un rol, con los cambios indicados.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function rolPayload(array $overrides = []): array
{
    return [
        'name' => 'supervisor',
        'permisos' => ['productos.ver', 'productos.crear'],
        ...$overrides,
    ];
}

beforeEach(function () {
    actingAsRol(Rol::Administrador);
});

it('lists roles with their permissions and users count, ordered by name and paginated', function () {
    User::factory()->count(2)->create()->each(fn (User $usuario) => $usuario->assignRole('vendedor'));

    $response = $this->getJson('/api/roles?per_page=1');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'administrador')
        ->assertJsonPath('data.0.users_count', 1)
        ->assertJsonCount(21, 'data.0.permisos')
        ->assertJsonPath('meta.total', 2);
});

it('shows a role with its permissions and users count', function () {
    $vendedor = Role::findByName('vendedor', 'web');

    $response = $this->getJson("/api/roles/{$vendedor->id}");

    $response->assertOk()
        ->assertJsonPath('data.name', 'vendedor')
        ->assertJsonPath('data.users_count', 0)
        ->assertJsonPath('data.permisos', [
            'categorias.ver', 'clientes.crear', 'clientes.editar', 'clientes.ver', 'productos.ver',
        ]);
});

it('creates a role with the given permissions', function () {
    $response = $this->postJson('/api/roles', rolPayload());

    $response->assertCreated()
        ->assertJsonPath('data.name', 'supervisor')
        ->assertJsonPath('data.permisos', ['productos.crear', 'productos.ver'])
        ->assertJsonPath('data.users_count', 0);
    expect(Role::findByName('supervisor', 'web')->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['productos.crear', 'productos.ver']);
});

it('requires the role fields', function () {
    $response = $this->postJson('/api/roles', []);

    $response->assertUnprocessable()->assertJsonValidationErrors([
        'name' => 'The name field is required.',
        'permisos' => 'The permisos field must be present.',
    ]);
});

it('rejects invalid role values', function (array $overrides, string $field, string $message) {
    $response = $this->postJson('/api/roles', rolPayload($overrides));

    $response->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);
})->with([
    'duplicated name' => [['name' => 'vendedor'], 'name', 'The name has already been taken.'],
    'unknown permission' => [['permisos' => ['facturas.anular']], 'permisos.0', 'The selected permisos.0 is invalid.'],
]);

it('renames a role and replaces its permissions', function () {
    $vendedor = Role::findByName('vendedor', 'web');

    $response = $this->putJson("/api/roles/{$vendedor->id}", rolPayload([
        'name' => 'vendedor senior',
        'permisos' => ['productos.ver'],
    ]));

    $response->assertOk()
        ->assertJsonPath('data.name', 'vendedor senior')
        ->assertJsonPath('data.permisos', ['productos.ver']);
    expect($vendedor->refresh()->permissions->pluck('name')->all())->toBe(['productos.ver']);
});

it('applies the new role permissions to its users', function () {
    $usuario = User::factory()->create()->assignRole('vendedor');
    $vendedor = Role::findByName('vendedor', 'web');

    $this->putJson("/api/roles/{$vendedor->id}", rolPayload(['name' => 'vendedor', 'permisos' => []]))
        ->assertOk();

    expect($usuario->refresh()->can('productos.ver'))->toBeFalse();
});

it('does not let the administrador role be edited or deleted', function (string $method, string $message) {
    $administrador = Role::findByName('administrador', 'web');

    $response = $this->json($method, "/api/roles/{$administrador->id}", rolPayload(['name' => 'administrador']));

    $response->assertConflict()->assertJsonPath('message', $message);
    expect($administrador->refresh()->permissions)->toHaveCount(21);
})->with([
    'edit' => ['PUT', 'No se puede editar el rol administrador.'],
    'delete' => ['DELETE', 'No se puede eliminar el rol administrador.'],
]);

it('does not delete a role that has users', function () {
    User::factory()->create()->assignRole('vendedor');
    $vendedor = Role::findByName('vendedor', 'web');

    $response = $this->deleteJson("/api/roles/{$vendedor->id}");

    $response->assertConflict()
        ->assertJsonPath('message', 'No se puede eliminar un rol que tiene usuarios asignados.');
    $this->assertModelExists($vendedor);
});

it('deletes a role without users', function () {
    $vendedor = Role::findByName('vendedor', 'web');

    $response = $this->deleteJson("/api/roles/{$vendedor->id}");

    $response->assertNoContent();
    $this->assertModelMissing($vendedor);
});

it('forbids a vendedor from managing roles', function () {
    actingAsRol(Rol::Vendedor);

    $this->getJson('/api/roles')->assertForbidden();
    $this->postJson('/api/roles', rolPayload())->assertForbidden();

    $this->assertDatabaseMissing('roles', ['name' => 'supervisor']);
});

it('returns 401 without a token', function (string $method, string $uri) {
    $this->app['auth']->forgetGuards();

    $response = $this->json($method, $uri);

    $response->assertUnauthorized();
})->with([
    'GET /api/roles' => ['GET', '/api/roles'],
    'POST /api/roles' => ['POST', '/api/roles'],
    'PUT /api/roles/1' => ['PUT', '/api/roles/1'],
    'DELETE /api/roles/1' => ['DELETE', '/api/roles/1'],
]);
