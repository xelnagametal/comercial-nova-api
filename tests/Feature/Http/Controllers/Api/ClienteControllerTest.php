<?php

use App\Models\Cliente;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Payload válido para crear o actualizar un cliente, con los cambios indicados.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function clientePayload(array $overrides = []): array
{
    return [
        'tipo_identificacion' => 'CEDULA',
        'identificacion' => '1712345678',
        'nombre' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'telefono' => '0991234567',
        'direccion' => 'Av. Amazonas N24-03',
        ...$overrides,
    ];
}

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
});

it('lists clients ordered by name and paginated', function () {
    Cliente::factory()->create(['nombre' => 'Zoila Mora']);
    Cliente::factory()->create(['nombre' => 'Ana Pérez']);

    $response = $this->getJson('/api/clientes?per_page=1');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nombre', 'Ana Pérez')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.last_page', 2);
});

it('shows a client', function () {
    $cliente = Cliente::factory()->create();

    $response = $this->getJson("/api/clientes/{$cliente->id}");

    $response->assertOk()
        ->assertJsonPath('data.identificacion', $cliente->identificacion)
        ->assertJsonPath('data.activo', true);
});

it('creates an active client', function () {
    $response = $this->postJson('/api/clientes', clientePayload());

    $response->assertCreated()
        ->assertJsonPath('data.identificacion', '1712345678')
        ->assertJsonPath('data.activo', true);
    $this->assertDatabaseHas('clientes', ['identificacion' => '1712345678', 'nombre' => 'Ana Pérez']);
});

it('requires the identification type, identification and name', function () {
    $response = $this->postJson('/api/clientes', []);

    $response->assertUnprocessable()->assertJsonValidationErrors([
        'tipo_identificacion' => 'The tipo identificacion field is required.',
        'identificacion' => 'The identificacion field is required.',
        'nombre' => 'The nombre field is required.',
    ]);
});

it('validates the identification according to its type', function (array $overrides, string $message) {
    $response = $this->postJson('/api/clientes', clientePayload($overrides));

    $response->assertUnprocessable()->assertJsonValidationErrors(['identificacion' => $message]);
})->with([
    'cédula without 10 digits' => [
        ['identificacion' => '171234567'],
        'The identificacion field must be 10 digits.',
    ],
    'RUC without 13 digits' => [
        ['tipo_identificacion' => 'RUC', 'identificacion' => '1712345678'],
        'The identificacion field must be 13 digits.',
    ],
    'passport with symbols' => [
        ['tipo_identificacion' => 'PASAPORTE', 'identificacion' => 'AB-123'],
        'The identificacion field must only contain letters and numbers.',
    ],
]);

it('rejects an unknown identification type', function () {
    $response = $this->postJson('/api/clientes', clientePayload(['tipo_identificacion' => 'DNI']));

    $response->assertUnprocessable()->assertJsonValidationErrors([
        'tipo_identificacion' => 'The selected tipo identificacion is invalid.',
    ]);
});

it('rejects an identification already used, even by a deleted client', function () {
    Cliente::factory()->create(['identificacion' => '1712345678'])->delete();

    $response = $this->postJson('/api/clientes', clientePayload());

    $response->assertUnprocessable()->assertJsonValidationErrors([
        'identificacion' => 'The identificacion has already been taken.',
    ]);
});

it('updates a client keeping its own identification', function () {
    $cliente = Cliente::factory()->create(['identificacion' => '1712345678']);

    $response = $this->putJson("/api/clientes/{$cliente->id}", clientePayload([
        'nombre' => 'Ana María Pérez',
        'activo' => false,
    ]));

    $response->assertOk()
        ->assertJsonPath('data.nombre', 'Ana María Pérez')
        ->assertJsonPath('data.activo', false);
    expect($cliente->refresh())
        ->nombre->toBe('Ana María Pérez')
        ->activo->toBeFalse();
});

it('soft deletes a client', function () {
    $cliente = Cliente::factory()->create();

    $response = $this->deleteJson("/api/clientes/{$cliente->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted($cliente);
});

it('returns 401 without a token', function (string $method, string $uri) {
    $this->app['auth']->forgetGuards();

    $response = $this->json($method, $uri);

    $response->assertUnauthorized();
})->with([
    'GET /api/clientes' => ['GET', '/api/clientes'],
    'POST /api/clientes' => ['POST', '/api/clientes'],
    'PUT /api/clientes/1' => ['PUT', '/api/clientes/1'],
    'DELETE /api/clientes/1' => ['DELETE', '/api/clientes/1'],
]);
