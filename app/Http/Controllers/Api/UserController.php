<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * CRUD del maestro de usuarios. Cada usuario tiene exactamente un rol.
 */
class UserController extends Controller
{
    /**
     * Lista los usuarios con sus roles y permisos, ordenados por nombre y paginados con ?per_page (máx. 100).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $usuarios = User::query()
            ->with(['roles.permissions', 'permissions'])
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return UserResource::collection($usuarios);
    }

    /**
     * Crea un usuario con el rol indicado (201).
     */
    public function store(UserRequest $request): UserResource
    {
        $usuario = DB::transaction(function () use ($request): User {
            $usuario = User::create($request->safe()->only(['name', 'email', 'password', 'activo']));
            $usuario->assignRole($request->validated('rol'));

            return $usuario;
        });

        return new UserResource($usuario->refresh());
    }

    /**
     * Muestra un usuario con sus roles y permisos.
     */
    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    /**
     * Actualiza un usuario y reemplaza su rol. Si se desactiva, se revocan sus tokens.
     */
    public function update(UserRequest $request, User $user): UserResource
    {
        $datos = $request->safe()->only(['name', 'email', 'password', 'activo']);

        if (blank($datos['password'] ?? null)) {
            unset($datos['password']);
        }

        DB::transaction(function () use ($user, $datos, $request): void {
            $user->update($datos);
            $user->syncRoles($request->validated('rol'));

            if (! $user->activo) {
                $user->tokens()->delete();
            }
        });

        return new UserResource($user);
    }

    /**
     * Elimina (borrado lógico) un usuario y revoca sus tokens (204).
     * Un usuario no puede eliminarse a sí mismo (409).
     */
    public function destroy(Request $request, User $user): Response
    {
        abort_if(
            $user->is($request->user()),
            Response::HTTP_CONFLICT,
            'No puede eliminar su propio usuario.',
        );

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->noContent();
    }
}
