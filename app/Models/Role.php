<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Support\Config;

/**
 * Rol de spatie con la relación de usuarios fija al modelo User.
 *
 * Spatie resuelve el modelo de usuarios a partir del guard del rol y, en un modelo vacío
 * (como el que usa withCount()), cae al guard por defecto, que dentro de una petición es
 * sanctum y no tiene modelo asociado. Todos los roles usan el guard web, cuyo modelo es User.
 */
class Role extends SpatieRole
{
    /**
     * Usuarios que tienen el rol.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->morphedByMany(
            User::class,
            'model',
            Config::modelHasRolesTable(),
            app(PermissionRegistrar::class)->pivotRole,
            Config::morphKey(),
        );
    }
}
