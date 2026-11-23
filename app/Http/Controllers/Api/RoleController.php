<?php

namespace App\Http\Controllers\Api;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Http\Resources\RoleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * CRUD del maestro de roles. Los permisos de cada rol se envían como estado final y se sincronizan.
 *
 * El rol administrador está protegido: siempre tiene todos los permisos y no se puede editar ni eliminar.
 */
class RoleController extends Controller
{
    /**
     * Lista los roles con sus permisos y cantidad de usuarios, ordenados por nombre y paginados con ?per_page (máx. 100).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $roles = Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->paginate($this->perPage($request));

        // Se cuenta sobre los roles ya cargados: spatie resuelve el modelo de usuarios a partir del
        // guard_name del rol, y en withCount() el modelo base está vacío (usaría el guard sanctum).
        $roles->loadCount('users');

        return RoleResource::collection($roles);
    }

    /**
     * Crea un rol con los permisos indicados (201).
     */
    public function store(RoleRequest $request): RoleResource
    {
        $rol = DB::transaction(function () use ($request): Role {
            $rol = Role::create(['name' => $request->validated('name'), 'guard_name' => 'web']);
            $rol->syncPermissions($request->validated('permisos'));

            return $rol;
        });

        return new RoleResource($rol->load('permissions')->loadCount('users'));
    }

    /**
     * Muestra un rol con sus permisos y cantidad de usuarios.
     */
    public function show(Role $role): RoleResource
    {
        return new RoleResource($role->load('permissions')->loadCount('users'));
    }

    /**
     * Renombra un rol y reemplaza sus permisos. El administrador no se puede editar (409).
     */
    public function update(RoleRequest $request, Role $role): RoleResource
    {
        $this->abortIfAdministrador($role, 'No se puede editar el rol administrador.');

        DB::transaction(function () use ($request, $role): void {
            $role->update(['name' => $request->validated('name')]);
            $role->syncPermissions($request->validated('permisos'));
        });

        return new RoleResource($role->load('permissions')->loadCount('users'));
    }

    /**
     * Elimina un rol sin usuarios asignados (204).
     * El administrador o un rol con usuarios responden 409.
     */
    public function destroy(Role $role): Response
    {
        $this->abortIfAdministrador($role, 'No se puede eliminar el rol administrador.');

        abort_if(
            $role->users()->exists(),
            Response::HTTP_CONFLICT,
            'No se puede eliminar un rol que tiene usuarios asignados.',
        );

        $role->delete();

        return response()->noContent();
    }

    /**
     * Responde 409 con el mensaje indicado si el rol es el administrador.
     */
    private function abortIfAdministrador(Role $role, string $mensaje): void
    {
        abort_if($role->name === Rol::Administrador->value, Response::HTTP_CONFLICT, $mensaje);
    }
}
