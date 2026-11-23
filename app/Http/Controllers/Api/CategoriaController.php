<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoriaRequest;
use App\Http\Resources\CategoriaResource;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * CRUD del maestro de categorías.
 */
class CategoriaController extends Controller
{
    /**
     * Lista las categorías ordenadas por nombre, paginadas con ?per_page (máx. 100).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $categorias = Categoria::query()->orderBy('nombre')->paginate($this->perPage($request));

        return CategoriaResource::collection($categorias);
    }

    /**
     * Crea una categoría (201).
     */
    public function store(CategoriaRequest $request): CategoriaResource
    {
        return new CategoriaResource(Categoria::create($request->validated()));
    }

    /**
     * Muestra una categoría.
     */
    public function show(Categoria $categoria): CategoriaResource
    {
        return new CategoriaResource($categoria);
    }

    /**
     * Actualiza una categoría.
     */
    public function update(CategoriaRequest $request, Categoria $categoria): CategoriaResource
    {
        $categoria->update($request->validated());

        return new CategoriaResource($categoria);
    }

    /**
     * Elimina (borrado lógico) una categoría sin productos asociados (204).
     * Si tiene productos responde 409.
     */
    public function destroy(Categoria $categoria): Response
    {
        abort_if(
            $categoria->productos()->exists(),
            Response::HTTP_CONFLICT,
            'No se puede eliminar una categoría que tiene productos.',
        );

        $categoria->delete();

        return response()->noContent();
    }
}
