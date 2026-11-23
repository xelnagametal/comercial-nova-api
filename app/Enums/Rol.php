<?php

namespace App\Enums;

/**
 * Roles de la aplicación (guard web, el que usa Sanctum).
 */
enum Rol: string
{
    case Administrador = 'administrador';
    case Vendedor = 'vendedor';

    /**
     * Permisos que otorga el rol.
     *
     * @return list<Permiso>
     */
    public function permisos(): array
    {
        return match ($this) {
            self::Administrador => Permiso::cases(),
            self::Vendedor => [
                Permiso::CategoriasVer,
                Permiso::ProductosVer,
                Permiso::ClientesVer,
                Permiso::ClientesCrear,
                Permiso::ClientesEditar,
            ],
        };
    }
}
