# Comercial Nova API

API REST construida con Laravel 13 y autenticada con tokens Bearer de [Laravel Sanctum](https://laravel.com/docs/sanctum). No usa sesión ni cookies: cada cliente recibe un Personal Access Token que envía en el header `Authorization`.

## Stack

| Componente | Versión |
| --- | --- |
| PHP | 8.5 (los paquetes bloqueados exigen 8.4.1 o superior) |
| Laravel | 13 |
| Laravel Sanctum | 4 |
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

## Endpoints

Todas las rutas llevan el prefijo `/api` y responden en JSON, también en los errores.

| Método | Ruta | Autenticación | Descripción |
| --- | --- | --- | --- |
| POST | `/api/register` | No | Crea un usuario y devuelve su token |
| POST | `/api/login` | No | Valida credenciales y devuelve un token nuevo |
| GET | `/api/me` | Bearer | Devuelve el usuario dueño del token |
| POST | `/api/logout` | Bearer | Revoca el token usado en la petición |
| GET | `/up` | No | Health check de Laravel |

`/api/register` y `/api/login` comparten un límite de 6 peticiones por minuto por IP; al superarlo responden `429`.

### Registro

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Ana Pérez",
    "email": "ana@example.com",
    "password": "secreto123",
    "password_confirmation": "secreto123",
    "device_name": "postman"
  }'
```

Respuesta `201`:

```json
{
  "user": {
    "id": 1,
    "name": "Ana Pérez",
    "email": "ana@example.com",
    "created_at": "2026-11-16T15:04:05.000000Z",
    "updated_at": "2026-11-16T15:04:05.000000Z"
  },
  "token": "1|Xk3..."
}
```

| Campo | Reglas |
| --- | --- |
| `name` | Obligatorio, máximo 255 caracteres |
| `email` | Obligatorio, email válido y único |
| `password` | Obligatorio, mínimo 8 caracteres, con `password_confirmation` |
| `device_name` | Obligatorio; nombre con el que se guarda el token |

### Login

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "ana@example.com",
    "password": "secreto123",
    "device_name": "postman"
  }'
```

Responde `200` con el mismo formato que el registro. Si las credenciales no son válidas responde `422` con el mismo mensaje tanto si el correo no existe como si la contraseña es incorrecta.

### Rutas protegidas

```bash
curl http://localhost:8000/api/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer 1|Xk3..."
```

Sin token, o con un token vencido o revocado, la respuesta es `401`.

`POST /api/logout` revoca únicamente el token de la petición y responde `204` sin cuerpo. Los tokens de otros dispositivos siguen activos.

### Tokens

- Cada login o registro emite un token nuevo; un usuario puede tener varios a la vez.
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
| `tests/Feature/Http/Controllers/Api/AuthControllerTest.php` | Registro, login, `/api/me`, logout, validaciones, vencimiento de tokens y límite de intentos |
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
app/Http/Controllers/Api/   Controladores de la API
app/Models/                 Modelos Eloquent
routes/api.php              Rutas de la API
routes/console.php          Tareas programadas
database/migrations/        Migraciones
tests/                      Tests de Pest (Feature y Unit)
Anybuild                    Definición de build para el despliegue
```
