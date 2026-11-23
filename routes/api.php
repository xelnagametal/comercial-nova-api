<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Ruta pública con límite de intentos contra fuerza bruta.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

// Rutas protegidas por token Bearer.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Maestros: cada acción exige el permiso {recurso}.{accion}.
    Route::apiResource('users', UserController::class)
        ->middlewareFor(['index', 'show'], 'can:usuarios.ver')
        ->middlewareFor('store', 'can:usuarios.crear')
        ->middlewareFor('update', 'can:usuarios.editar')
        ->middlewareFor('destroy', 'can:usuarios.eliminar');

    Route::apiResource('categorias', CategoriaController::class)
        ->middlewareFor(['index', 'show'], 'can:categorias.ver')
        ->middlewareFor('store', 'can:categorias.crear')
        ->middlewareFor('update', 'can:categorias.editar')
        ->middlewareFor('destroy', 'can:categorias.eliminar');

    Route::apiResource('clientes', ClienteController::class)
        ->middlewareFor(['index', 'show'], 'can:clientes.ver')
        ->middlewareFor('store', 'can:clientes.crear')
        ->middlewareFor('update', 'can:clientes.editar')
        ->middlewareFor('destroy', 'can:clientes.eliminar');

    Route::apiResource('productos', ProductoController::class)
        ->middlewareFor(['index', 'show'], 'can:productos.ver')
        ->middlewareFor('store', 'can:productos.crear')
        ->middlewareFor('update', 'can:productos.editar')
        ->middlewareFor('destroy', 'can:productos.eliminar');
});
