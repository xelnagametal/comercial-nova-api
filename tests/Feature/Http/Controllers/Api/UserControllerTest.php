<?php

use App\Enums\Rol;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Payload válido para crear o actualizar un usuario, con los cambios indicados.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function usuarioPayload(array $overrides = []): array
{
    return [
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'password' => 'secreto123',
        'password_confirmation' => 'secreto123',
        'roles' => ['vendedor'],
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->admin = actingAsRol(Rol::Administrador);
});

it('lists users with their roles, ordered by name and paginated', function () {
    $this->admin->update(['name' => 'Zoila Admin']);
    User::factory()->create(['name' => 'Ana Pérez'])->assignRole('vendedor');

    $response = $this->getJson('/api/users?per_page=1');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Ana Pérez')
        ->assertJsonPath('data.0.roles', ['vendedor'])
        ->assertJsonPath('meta.total', 2)
        ->assertJsonMissingPath('data.0.password');
});

it('shows a user with roles and permissions', function () {
    $response = $this->getJson("/api/users/{$this->admin->id}");

    $response->assertOk()
        ->assertJsonPath('data.roles', ['administrador'])
        ->assertJsonCount(21, 'data.permisos');
});

it('creates a user with the given role and a hashed password', function () {
    $response = $this->postJson('/api/users', usuarioPayload());

    $response->assertCreated()
        ->assertJsonPath('data.email', 'ana@example.com')
        ->assertJsonPath('data.activo', true)
        ->assertJsonPath('data.roles', ['vendedor']);

    $usuario = User::firstWhere('email', 'ana@example.com');
    expect($usuario->hasRole('vendedor'))->toBeTrue();
    expect(Hash::check('secreto123', $usuario->password))->toBeTrue();
});

it('requires the user fields', function () {
    $response = $this->postJson('/api/users', []);

    $response->assertUnprocessable()->assertJsonValidationErrors([
        'name' => 'The name field is required.',
        'email' => 'The email field is required.',
        'password' => 'The password field is required.',
        'roles' => 'The roles field is required.',
    ]);
});

it('rejects invalid user values', function (array $overrides, string $field, string $message) {
    User::factory()->create(['email' => 'repetido@example.com']);

    $response = $this->postJson('/api/users', usuarioPayload($overrides));

    $response->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);
})->with([
    'duplicated email' => [['email' => 'repetido@example.com'], 'email', 'The email has already been taken.'],
    'empty roles' => [['roles' => []], 'roles', 'The roles field is required.'],
    'unknown role' => [['roles' => ['gerente']], 'roles.0', 'The selected roles.0 is invalid.'],
    'unconfirmed password' => [
        ['password_confirmation' => 'otra-cosa'],
        'password',
        'The password field confirmation does not match.',
    ],
]);

it('updates a user keeping the password when none is sent', function () {
    $usuario = User::factory()->create(['email' => 'ana@example.com', 'password' => 'original123']);
    $usuario->assignRole('vendedor');

    $response = $this->putJson("/api/users/{$usuario->id}", usuarioPayload([
        'name' => 'Ana María Pérez',
        'password' => null,
        'password_confirmation' => null,
    ]));

    $response->assertOk()->assertJsonPath('data.name', 'Ana María Pérez');
    expect(Hash::check('original123', $usuario->refresh()->password))->toBeTrue();
});

it('replaces the roles of a user with the given list', function () {
    $usuario = User::factory()->create(['email' => 'ana@example.com']);
    $usuario->assignRole('vendedor');

    $response = $this->putJson("/api/users/{$usuario->id}", usuarioPayload(['roles' => ['administrador']]));

    $response->assertOk()->assertJsonPath('data.roles', ['administrador']);
    expect($usuario->refresh()->getRoleNames()->all())->toBe(['administrador']);
});

it('creates a user with several roles, including one created through the api', function () {
    Role::create(['name' => 'supervisor', 'guard_name' => 'web'])->givePermissionTo('productos.crear');

    $response = $this->postJson('/api/users', usuarioPayload(['roles' => ['vendedor', 'supervisor']]));

    $response->assertCreated()->assertJsonPath('data.roles', ['vendedor', 'supervisor']);
    expect(User::firstWhere('email', 'ana@example.com')->can('productos.crear'))->toBeTrue();
});

it('does not let an administrador remove its own administrador role', function () {
    $response = $this->putJson("/api/users/{$this->admin->id}", usuarioPayload([
        'email' => $this->admin->email,
        'roles' => ['vendedor'],
    ]));

    $response->assertConflict()
        ->assertJsonPath('message', 'No puede quitarse a sí mismo el rol administrador.');
    expect($this->admin->refresh()->getRoleNames()->all())->toBe(['administrador']);
});

it('revokes the tokens of a user when it is deactivated', function () {
    $usuario = User::factory()->create(['email' => 'ana@example.com']);
    $usuario->assignRole('vendedor');
    $usuario->createToken('iphone');

    $response = $this->putJson("/api/users/{$usuario->id}", usuarioPayload(['activo' => false]));

    $response->assertOk()->assertJsonPath('data.activo', false);
    expect($usuario->tokens()->count())->toBe(0);
});

it('soft deletes a user and revokes its tokens', function () {
    $usuario = User::factory()->create();
    $usuario->createToken('iphone');

    $response = $this->deleteJson("/api/users/{$usuario->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted($usuario);
    expect($usuario->tokens()->count())->toBe(0);
});

it('does not let a user delete itself', function () {
    $response = $this->deleteJson("/api/users/{$this->admin->id}");

    $response->assertConflict()->assertJsonPath('message', 'No puede eliminar su propio usuario.');
    $this->assertNotSoftDeleted($this->admin);
});

it('forbids a vendedor from managing users', function () {
    actingAsRol(Rol::Vendedor);

    $this->getJson('/api/users')->assertForbidden();
    $this->postJson('/api/users', usuarioPayload())->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
});

it('returns 401 without a token', function (string $method, string $uri) {
    $this->app['auth']->forgetGuards();

    $response = $this->json($method, $uri);

    $response->assertUnauthorized();
})->with([
    'GET /api/users' => ['GET', '/api/users'],
    'POST /api/users' => ['POST', '/api/users'],
    'PUT /api/users/1' => ['PUT', '/api/users/1'],
    'DELETE /api/users/1' => ['DELETE', '/api/users/1'],
]);
