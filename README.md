# Comercial Nova API

API REST construida con Laravel 13 y autenticada con tokens Bearer de [Laravel Sanctum](https://laravel.com/docs/sanctum). No usa sesión ni cookies: cada cliente recibe un Personal Access Token que envía en el header `Authorization`.

## Stack

| Componente | Versión |
| --- | --- |
| PHP | 8.5 (los paquetes bloqueados exigen 8.4.1 o superior) |
| Laravel | 13 |
| Laravel Sanctum | 4 |
| spatie/laravel-permission | 8 |
| Pest | 5 |
| Base de datos | SQLite por defecto |
| Node | 24 (Vite 8 y Tailwind CSS 4) |

## Instalación

```bash
git clone git@github.com:xelnagametal/comercial-nova-api.git
cd comercial-nova-api
composer run setup
```

`composer run setup` instala las dependencias de PHP y Node, crea `.env` a partir de `.env.example`, genera `APP_KEY`, ejecuta las migraciones y compila los assets.

La configuración por defecto usa SQLite (`database/database.sqlite`). Para otro motor, edita las variables `DB_*` de `.env` y vuelve a ejecutar `php artisan migrate`.

## Desarrollo

```bash
composer run dev
```

Levanta los procesos de desarrollo. La API queda disponible en `http://localhost:8000/api`.

Si solo necesitas el servidor HTTP, usa `php artisan serve`.

## Datos de prueba

```bash
php artisan migrate:fresh --seed
```

Crea los roles y permisos, un administrador (`admin@example.com` / `password`), 20 vendedores (contraseña `password`), categorías, clientes, productos con su inventario inicial y 30 facturas emitidas por los vendedores.

## Endpoints

Todas las rutas llevan el prefijo `/api` y responden en JSON, también en los errores.

| Método | Ruta | Autenticación | Descripción |
| --- | --- | --- | --- |
| POST | `/api/login` | No | Valida credenciales y devuelve un token nuevo con los roles y permisos del usuario |
| GET | `/api/me` | Bearer | Devuelve el usuario dueño del token con sus roles y permisos |
| POST | `/api/logout` | Bearer | Revoca el token usado en la petición |
| GET | `/up` | No | Health check de Laravel |

`/api/login` admite 6 peticiones por minuto por IP; al superarlas responde `429`.

No hay registro público: los usuarios los crea un administrador desde `/api/users`.

### Login

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password",
    "device_name": "postman"
  }'
```

Respuesta `200`:

```json
{
  "user": {
    "id": 1,
    "name": "Administrador",
    "email": "admin@example.com",
    "activo": true,
    "roles": ["administrador"],
    "permisos": ["categorias.crear", "categorias.editar", "..."],
    "created_at": "2026-11-23T15:04:05.000000Z",
    "updated_at": "2026-11-23T15:04:05.000000Z"
  },
  "token": "1|Xk3..."
}
```

Responde `422` con el mismo mensaje si el correo no existe, la contraseña es incorrecta, o el usuario está inactivo o eliminado.

### Rutas protegidas

```bash
curl http://localhost:8000/api/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer 1|Xk3..."
```

Sin token, o con un token vencido o revocado, la respuesta es `401`. Con token pero sin el permiso necesario, la respuesta es `403`.

`POST /api/logout` revoca únicamente el token de la petición y responde `204` sin cuerpo. Los tokens de otros dispositivos siguen activos.

### Maestros

`users`, `categorias`, `clientes` y `productos` exponen el mismo CRUD:

| Método | Ruta | Permiso | Respuesta |
| --- | --- | --- | --- |
| GET | `/api/{recurso}?per_page=15&page=1` | `{recurso}.ver` | `200`, paginado con `data`, `links` y `meta` |
| POST | `/api/{recurso}` | `{recurso}.crear` | `201` |
| GET | `/api/{recurso}/{id}` | `{recurso}.ver` | `200` |
| PUT/PATCH | `/api/{recurso}/{id}` | `{recurso}.editar` | `200` |
| DELETE | `/api/{recurso}/{id}` | `{recurso}.eliminar` | `204`, borrado lógico |

- El permiso de `/api/users` usa el recurso `usuarios` (por ejemplo `usuarios.ver`).
- `per_page` vale 15 por defecto y se limita entre 1 y 100.
- **Usuarios:** se envía `rol` (`administrador` o `vendedor`). Al editar, la contraseña es opcional. Desactivar (`activo: false`) o eliminar un usuario revoca sus tokens. Un usuario no puede eliminarse a sí mismo (`409`).
- **Categorías:** no se puede eliminar una categoría con productos (`409`).
- **Productos:** no aceptan `stock`. Al editar hay que enviar la `version` leída; si otro usuario lo modificó antes, responde `409` (bloqueo optimista).

### Roles y permisos

Los permisos siguen el formato `{recurso}.{accion}` y están definidos en `app/Enums/Permiso.php`; los roles y sus permisos, en `app/Enums/Rol.php`. Tras cambiarlos, ejecuta `php artisan db:seed --class=RolesYPermisosSeeder`.

| Rol | Permisos |
| --- | --- |
| `administrador` | Todos |
| `vendedor` | `categorias.ver`, `productos.ver`, `clientes.ver`, `clientes.crear`, `clientes.editar` |

### Tokens

- Cada login emite un token nuevo; un usuario puede tener varios a la vez.
- Los tokens vencen a las 24 horas (`expiration` en `config/sanctum.php`).
- El scheduler ejecuta `sanctum:prune-expired --hours=24` una vez al día para borrar los tokens vencidos. En local se activa con `php artisan schedule:work`; en producción hace falta un cron que ejecute `php artisan schedule:run` cada minuto.

## Tests

```bash
composer run test
```

Para ejecutar un archivo o un test concreto:

```bash
php artisan test --compact tests/Feature/Http/Controllers/Api/AuthControllerTest.php
php artisan test --compact --filter="returns 401 when no token is provided"
```

| Archivo | Qué cubre |
| --- | --- |
| `tests/Feature/Http/Controllers/Api/AuthControllerTest.php` | Login (con roles y permisos), `/api/me`, logout, usuarios inactivos o eliminados, vencimiento de tokens y límite de intentos |
| `tests/Feature/Http/Controllers/Api/UserControllerTest.php` | CRUD de usuarios, asignación de roles, revocación de tokens y acceso denegado a vendedores |
| `tests/Feature/Http/Controllers/Api/CategoriaControllerTest.php`, `ClienteControllerTest.php`, `ProductoControllerTest.php` | CRUD de los maestros, paginado, validaciones, permisos y bloqueo optimista de productos |
| `tests/Feature/Database/RolesYPermisosSeederTest.php` | Permisos de cada rol |
| `tests/Unit/ArchTest.php` | Reglas de arquitectura: sin funciones de depuración ni funciones inseguras, y controladores que extienden el controlador base |

Los tests usan SQLite en memoria (ver `phpunit.xml`).

## Estilo de código

```bash
vendor/bin/pint --dirty
```

## Despliegue

El build de producción se define en el archivo `Anybuild`:

- Fija PHP 8.5, porque el valor por defecto de Anybuild (8.3) no cumple el mínimo de las dependencias.
- Ejecuta un script de Composer propio en lugar de `post-update-cmd`, que depende de un comando de Laravel Boost disponible solo en local.
- Ejecuta `php artisan migrate --force` después de cada despliegue.

Las variables de producción van en `.env.production`, que no se versiona.

## Estructura

```
app/Enums/                  Roles y permisos
app/Http/Controllers/Api/   Controladores de la API
app/Http/Requests/          Validación de los maestros
app/Http/Resources/         Formato JSON de las respuestas
app/Models/                 Modelos Eloquent
routes/api.php              Rutas de la API
routes/console.php          Tareas programadas
database/migrations/        Migraciones
database/factories/         Factories
database/seeders/           Seeders de datos de prueba
tests/                      Tests de Pest (Feature y Unit)
Anybuild                    Definición de build para el despliegue
```
