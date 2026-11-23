<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClienteRequest;
use App\Http\Resources\ClienteResource;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * CRUD del maestro de clientes.
 */
class ClienteController extends Controller
{
    /**
     * Lista los clientes ordenados por nombre, paginados con ?per_page (máx. 100).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $clientes = Cliente::query()->orderBy('nombre')->paginate($this->perPage($request));

        return ClienteResource::collection($clientes);
    }

    /**
     * Crea un cliente (201).
     */
    public function store(ClienteRequest $request): ClienteResource
    {
        return new ClienteResource(Cliente::create($request->validated())->refresh());
    }

    /**
     * Muestra un cliente.
     */
    public function show(Cliente $cliente): ClienteResource
    {
        return new ClienteResource($cliente);
    }

    /**
     * Actualiza un cliente.
     */
    public function update(ClienteRequest $request, Cliente $cliente): ClienteResource
    {
        $cliente->update($request->validated());

        return new ClienteResource($cliente);
    }

    /**
     * Elimina (borrado lógico) un cliente; sus facturas se conservan (204).
     */
    public function destroy(Cliente $cliente): Response
    {
        $cliente->delete();

        return response()->noContent();
    }
}
