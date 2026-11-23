<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermisoResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * Catálogo de permisos (solo lectura). Los permisos se definen en el enum Permiso
 * porque cada uno debe estar verificado por una ruta.
 */
class PermisoController extends Controller
{
    /**
     * Lista todos los permisos agrupados por recurso, ordenados por nombre (sin paginar).
     */
    public function index(): JsonResponse
    {
        $grupos = Permission::query()
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permiso): string => Str::before($permiso->name, '.'))
            ->map(fn (Collection $permisos, string $recurso): array => [
                'recurso' => $recurso,
                'permisos' => PermisoResource::collection($permisos->values()),
            ])
            ->values();

        return response()->json(['data' => $grupos]);
    }
}
