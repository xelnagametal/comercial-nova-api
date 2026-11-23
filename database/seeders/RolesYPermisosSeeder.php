<?php

namespace Database\Seeders;

use App\Enums\Permiso as PermisoEnum;
use App\Enums\Rol as RolEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea los permisos y roles definidos en los enums.
 * Es idempotente: se puede volver a ejecutar tras cambiar los enums.
 *
 * El administrador siempre se sincroniza con todos los permisos. Los demás roles reciben
 * sus permisos por defecto solo al crearse, para no pisar los cambios hechos desde la API.
 *
 * La caché de spatie se limpia a mano tras cada paso porque DatabaseSeeder usa
 * WithoutModelEvents y spatie solo la limpia mediante eventos de modelo.
 */
class RolesYPermisosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (PermisoEnum::cases() as $permiso) {
            Permission::findOrCreate($permiso->value, 'web');
        }

        $registrar->forgetCachedPermissions();

        foreach (RolEnum::cases() as $rol) {
            $role = Role::findOrCreate($rol->value, 'web');

            if ($rol === RolEnum::Administrador || $role->wasRecentlyCreated) {
                $role->syncPermissions(
                    array_map(fn (PermisoEnum $permiso): string => $permiso->value, $rol->permisos()),
                );
            }
        }

        $registrar->forgetCachedPermissions();
    }
}
