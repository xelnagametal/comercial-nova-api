<?php

namespace App\Enums;

/**
 * Permisos de la aplicación con el formato {recurso}.{accion}.
 */
enum Permiso: string
{
    case UsuariosVer = 'usuarios.ver';
    case UsuariosCrear = 'usuarios.crear';
    case UsuariosEditar = 'usuarios.editar';
    case UsuariosEliminar = 'usuarios.eliminar';

    case RolesVer = 'roles.ver';
    case RolesCrear = 'roles.crear';
    case RolesEditar = 'roles.editar';
    case RolesEliminar = 'roles.eliminar';

    case PermisosVer = 'permisos.ver';

    case CategoriasVer = 'categorias.ver';
    case CategoriasCrear = 'categorias.crear';
    case CategoriasEditar = 'categorias.editar';
    case CategoriasEliminar = 'categorias.eliminar';

    case ClientesVer = 'clientes.ver';
    case ClientesCrear = 'clientes.crear';
    case ClientesEditar = 'clientes.editar';
    case ClientesEliminar = 'clientes.eliminar';

    case ProductosVer = 'productos.ver';
    case ProductosCrear = 'productos.crear';
    case ProductosEditar = 'productos.editar';
    case ProductosEliminar = 'productos.eliminar';
}
