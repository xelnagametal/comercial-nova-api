<?php

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Payload válido para crear un producto en la categoría indicada, con los cambios indicados.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function productoPayload(Categoria $categoria, array $overrides = []): array
{
    return [
        'categoria_id' => $categoria->id,
        'codigo' => 'PRD-00001',
        'nombre' => 'Agua sin gas 500 ml',
        'precio' => 0.75,
        'porcentaje_iva' => 15,
        ...$overrides,
    ];
}

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
});

it('lists products with their category, ordered by name and paginated', function () {
    $categoria = Categoria::factory()->create(['nombre' => 'Bebidas']);
    Producto::factory()->for($categoria)->create(['nombre' => 'Jugo']);
    Producto::factory()->for($categoria)->create(['nombre' => 'Agua']);

    $response = $this->getJson('/api/productos?per_page=1');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nombre', 'Agua')
        ->assertJsonPath('data.0.categoria.nombre', 'Bebidas')
        ->assertJsonPath('meta.total', 2);
});

it('shows a product with its category', function () {
    $producto = Producto::factory()->create();

    $response = $this->getJson("/api/productos/{$producto->id}");

    $response->assertOk()
        ->assertJsonPath('data.codigo', $producto->codigo)
        ->assertJsonPath('data.categoria.id', $producto->categoria_id);
});

it('creates a product with no stock and version 1, ignoring a sent stock', function () {
    $categoria = Categoria::factory()->create();

    $response = $this->postJson('/api/productos', productoPayload($categoria, ['stock' => 50]));

    $response->assertCreated()
        ->assertJsonPath('data.codigo', 'PRD-00001')
        ->assertJsonPath('data.precio', '0.75')
        ->assertJsonPath('data.stock', 0)
        ->assertJsonPath('data.version', 1);
    $this->assertDatabaseHas('productos', ['codigo' => 'PRD-00001', 'stock' => 0]);
});

it('requires the product fields', function () {
    $response = $this->postJson('/api/productos', []);

    $response->assertUnprocessable()->assertJsonValidationErrors([
        'categoria_id' => 'The categoria id field is required.',
        'codigo' => 'The codigo field is required.',
        'nombre' => 'The nombre field is required.',
        'precio' => 'The precio field is required.',
        'porcentaje_iva' => 'The porcentaje iva field is required.',
    ]);
});

it('rejects invalid product values', function (Closure $overrides, string $field, string $message) {
    $categoria = Categoria::factory()->create();
    Producto::factory()->create(['codigo' => 'PRD-99999']);

    $response = $this->postJson('/api/productos', productoPayload($categoria, $overrides()));

    $response->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);
})->with([
    'deleted category' => [
        fn () => ['categoria_id' => tap(Categoria::factory()->create())->delete()->id],
        'categoria_id',
        'The selected categoria id is invalid.',
    ],
    'duplicated code' => [
        fn () => ['codigo' => 'PRD-99999'],
        'codigo',
        'The codigo has already been taken.',
    ],
    'negative price' => [
        fn () => ['precio' => -1],
        'precio',
        'The precio field must be at least 0.',
    ],
    'price with three decimals' => [
        fn () => ['precio' => 1.255],
        'precio',
        'The precio field must have 0-2 decimal places.',
    ],
    'VAT rate other than 0 or 15' => [
        fn () => ['porcentaje_iva' => 12],
        'porcentaje_iva',
        'The selected porcentaje iva is invalid.',
    ],
]);

it('updates a product and increments its version', function () {
    $producto = Producto::factory()->create(['stock' => 20, 'version' => 3]);

    $response = $this->putJson("/api/productos/{$producto->id}", productoPayload($producto->categoria, [
        'codigo' => $producto->codigo,
        'nombre' => 'Agua con gas 500 ml',
        'version' => 3,
    ]));

    $response->assertOk()
        ->assertJsonPath('data.nombre', 'Agua con gas 500 ml')
        ->assertJsonPath('data.version', 4)
        ->assertJsonPath('data.stock', 20);
    expect($producto->refresh())
        ->nombre->toBe('Agua con gas 500 ml')
        ->version->toBe(4)
        ->stock->toBe(20);
});

it('requires the version when updating a product', function () {
    $producto = Producto::factory()->create();

    $response = $this->putJson("/api/productos/{$producto->id}", productoPayload($producto->categoria, [
        'codigo' => $producto->codigo,
    ]));

    $response->assertUnprocessable()->assertJsonValidationErrors([
        'version' => 'The version field is required.',
    ]);
});

it('returns 409 when the product was modified by someone else', function () {
    $producto = Producto::factory()->create(['nombre' => 'Original', 'version' => 2]);

    $response = $this->putJson("/api/productos/{$producto->id}", productoPayload($producto->categoria, [
        'codigo' => $producto->codigo,
        'nombre' => 'Cambio con versión vieja',
        'version' => 1,
    ]));

    $response->assertConflict()->assertJsonPath(
        'message',
        'El producto fue modificado por otro usuario. Vuelva a cargarlo e intente de nuevo.',
    );
    expect($producto->refresh())
        ->nombre->toBe('Original')
        ->version->toBe(2);
});

it('soft deletes a product', function () {
    $producto = Producto::factory()->create();

    $response = $this->deleteJson("/api/productos/{$producto->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted($producto);
});

it('returns 401 without a token', function (string $method, string $uri) {
    $this->app['auth']->forgetGuards();

    $response = $this->json($method, $uri);

    $response->assertUnauthorized();
})->with([
    'GET /api/productos' => ['GET', '/api/productos'],
    'POST /api/productos' => ['POST', '/api/productos'],
    'PUT /api/productos/1' => ['PUT', '/api/productos/1'],
    'DELETE /api/productos/1' => ['DELETE', '/api/productos/1'],
]);
