<?php

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
});

it('lists categories ordered by name and paginated with per_page', function () {
    Categoria::factory()->create(['nombre' => 'Snacks']);
    Categoria::factory()->create(['nombre' => 'Bebidas']);
    Categoria::factory()->create(['nombre' => 'Lácteos']);

    $response = $this->getJson('/api/categorias?per_page=2');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.nombre', 'Bebidas')
        ->assertJsonPath('data.1.nombre', 'Lácteos')
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 3);
});

it('caps per_page at 100', function () {
    $response = $this->getJson('/api/categorias?per_page=500');

    $response->assertOk()->assertJsonPath('meta.per_page', 100);
});

it('shows a category', function () {
    $categoria = Categoria::factory()->create();

    $response = $this->getJson("/api/categorias/{$categoria->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $categoria->id)
        ->assertJsonPath('data.nombre', $categoria->nombre);
});

it('creates a category', function () {
    $response = $this->postJson('/api/categorias', ['nombre' => 'Bebidas']);

    $response->assertCreated()->assertJsonPath('data.nombre', 'Bebidas');
    $this->assertDatabaseHas('categorias', ['nombre' => 'Bebidas']);
});

it('rejects a missing or duplicated name', function (array $payload, string $message) {
    Categoria::factory()->create(['nombre' => 'Bebidas']);

    $response = $this->postJson('/api/categorias', $payload);

    $response->assertUnprocessable()->assertJsonValidationErrors(['nombre' => $message]);
})->with([
    'missing' => [[], 'The nombre field is required.'],
    'duplicated' => [['nombre' => 'Bebidas'], 'The nombre has already been taken.'],
]);

it('updates a category keeping its own name', function () {
    $categoria = Categoria::factory()->create(['nombre' => 'Bebidas']);

    $this->putJson("/api/categorias/{$categoria->id}", ['nombre' => 'Bebidas'])->assertOk();
    $response = $this->putJson("/api/categorias/{$categoria->id}", ['nombre' => 'Gaseosas']);

    $response->assertOk()->assertJsonPath('data.nombre', 'Gaseosas');
    expect($categoria->refresh()->nombre)->toBe('Gaseosas');
});

it('soft deletes a category without products', function () {
    $categoria = Categoria::factory()->create();

    $response = $this->deleteJson("/api/categorias/{$categoria->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted($categoria);
    $this->getJson("/api/categorias/{$categoria->id}")->assertNotFound();
});

it('refuses to delete a category that has products', function () {
    $categoria = Categoria::factory()->has(Producto::factory())->create();

    $response = $this->deleteJson("/api/categorias/{$categoria->id}");

    $response->assertConflict()
        ->assertJsonPath('message', 'No se puede eliminar una categoría que tiene productos.');
    $this->assertNotSoftDeleted($categoria);
});

it('returns 401 without a token', function (string $method, string $uri) {
    $this->app['auth']->forgetGuards();

    $response = $this->json($method, $uri);

    $response->assertUnauthorized();
})->with([
    'GET /api/categorias' => ['GET', '/api/categorias'],
    'POST /api/categorias' => ['POST', '/api/categorias'],
    'PUT /api/categorias/1' => ['PUT', '/api/categorias/1'],
    'DELETE /api/categorias/1' => ['DELETE', '/api/categorias/1'],
]);
