<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductoRequest;
use App\Http\Resources\ProductoResource;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * CRUD del maestro de productos.
 */
class ProductoController extends Controller
{
    /**
     * Lista los productos con su categoría, ordenados por nombre y paginados con ?per_page (máx. 100).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $productos = Producto::query()
            ->with('categoria')
            ->orderBy('nombre')
            ->paginate($this->perPage($request));

        return ProductoResource::collection($productos);
    }

    /**
     * Crea un producto sin stock; el stock entra con movimientos de inventario (201).
     */
    public function store(ProductoRequest $request): ProductoResource
    {
        $producto = Producto::create($request->validated())->refresh();

        return new ProductoResource($producto->load('categoria'));
    }

    /**
     * Muestra un producto con su categoría.
     */
    public function show(Producto $producto): ProductoResource
    {
        return new ProductoResource($producto->load('categoria'));
    }

    /**
     * Actualiza un producto si la versión enviada coincide con la guardada y la incrementa.
     * Si otro usuario lo modificó antes responde 409.
     */
    public function update(ProductoRequest $request, Producto $producto): ProductoResource
    {
        $datos = $request->safe()->except('version');
        $versionLeida = $request->integer('version');

        $filasActualizadas = Producto::query()
            ->whereKey($producto->id)
            ->where('version', $versionLeida)
            ->update([...$datos, 'version' => $versionLeida + 1]);

        abort_if(
            $filasActualizadas === 0,
            Response::HTTP_CONFLICT,
            'El producto fue modificado por otro usuario. Vuelva a cargarlo e intente de nuevo.',
        );

        return new ProductoResource($producto->refresh()->load('categoria'));
    }

    /**
     * Elimina (borrado lógico) un producto (204).
     */
    public function destroy(Producto $producto): Response
    {
        $producto->delete();

        return response()->noContent();
    }
}
