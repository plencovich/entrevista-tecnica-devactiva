# CMS API

API REST modular construida con Laravel 12, Laravel Sanctum, Docker y SQLite.

## Stack

- PHP 8.4.25.
- Laravel 12.68.0.
- Laravel Sanctum 4.3.3.
- Composer 2.10.2.
- SQLite 3.40.1.
- PHPUnit 11.5.56.
- Laravel Pint 1.30.5.

## Requisitos

- Git.
- Docker Engine.
- Docker Compose v2 (`docker compose`).

PHP y Composer se ejecutan dentro del contenedor; no es necesario instalarlos en el host.

## Instalación

```bash
git clone <repository-url>
cd entrevista-tecnica
cp .env.example .env
docker compose up -d --build
docker compose ps
docker compose exec app php artisan migrate:fresh --seed
```

`migrate:fresh` recrea la base de datos y elimina sus datos existentes. Para conservarlos, usar `docker compose exec app php artisan migrate --seed`.

Durante el arranque, el contenedor `app` instala las dependencias Composer, crea `database/database.sqlite` si no existe, genera `APP_KEY` cuando está vacío y ejecuta las migrations pendientes.

La aplicación queda disponible en [http://localhost:8000](http://localhost:8000). El healthcheck está expuesto en [http://localhost:8000/up](http://localhost:8000/up).

## Configuración

`.env.example` contiene valores seguros para desarrollo. Las variables principales son:

- `APP_PORT`: puerto HTTP publicado; por defecto `8000`.
- `HOST_UID` y `HOST_GID`: usuario y grupo del contenedor; por defecto `1000`.
- `APP_URL`: URL local de la aplicación.

La base SQLite persiste en `database/database.sqlite` y está excluida de Git. No guardar secretos reales en `.env.example` ni versionar `.env`.

Si se cambian `HOST_UID` o `HOST_GID`, reconstruir la imagen:

```bash
docker compose build --no-cache app
docker compose up -d
```

## Modelo de datos

- `User 1 --- N Article`: cada artículo pertenece a un autor mediante `author_id`.
- `Article N --- N Category`: `article_category` relaciona artículos y categorías sin ID artificial.

`users.email` y `articles.slug` son únicos. El pivot impide duplicar una asociación artículo-categoría. Un usuario con artículos y una categoría asociada no pueden eliminarse por integridad referencial. Al eliminar un artículo, sus asociaciones del pivot se eliminan en cascada.

Valores permitidos:

- `user.role`: `admin`, `editor`.
- `user.status`: `active`, `inactive`.
- `article.status`: `draft`, `published`.
- `category.status`: `active`, `inactive`.

Los emails se normalizan a minúsculas y los passwords se almacenan mediante el hashing nativo de Laravel.

El autor de un artículo siempre es el usuario autenticado que lo crea y no puede modificarse desde la API. Todo artículo se crea con al menos una categoría. El slug se deriva del título, se regenera cuando el título cambia y resuelve colisiones con sufijos incrementales (`mi-articulo`, `mi-articulo-2`, ...).

La publicación mantiene estas invariantes:

- Un artículo `draft` siempre tiene `published_at: null`.
- Un artículo `published` sin fecha explícita recibe la fecha actual.
- Una fecha válida enviada al publicar se conserva.
- Volver de `published` a `draft` limpia `published_at`.

## Autenticación

La API usa tokens personales de Sanctum mediante este header:

```text
Authorization: Bearer <token>
```

Un usuario inactivo no puede obtener tokens nuevos. El logout revoca exclusivamente el token usado en esa petición.

### Usuario local

El seeder crea o actualiza un usuario destinado exclusivamente al entorno local:

```text
email: admin@example.com
password: password
```

Ejecutar el seeder con:

```bash
docker compose exec app php artisan db:seed
```

Estas credenciales son de demostración y no deben utilizarse en producción.

### Login

```bash
curl --request POST http://localhost:8000/api/v1/auth/login \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{"email":"admin@example.com","password":"password"}'
```

Una autenticación correcta responde `200` con `token`, `token_type: Bearer` y los datos públicos del usuario. Credenciales inválidas responden `401`; un usuario inactivo responde `403`.

### Usuario autenticado

```bash
curl http://localhost:8000/api/v1/auth/me \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <token>'
```

### Logout

```bash
curl --request POST http://localhost:8000/api/v1/auth/logout \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <token>'
```

El endpoint responde `204 No Content`. El token revocado no puede reutilizarse.

## Roles y permisos

| Recurso / acción | Admin | Editor |
| --- | --- | --- |
| Usuarios | CRUD completo | Sin acceso administrativo |
| Categorías: listar/ver | Sí | Sí |
| Categorías: crear/editar/eliminar | Sí | No |
| Artículos: listar | Todos | Sólo propios |
| Artículos: ver | Todos | Sólo propios |
| Artículos: crear | Sí, si está activo | Sí, si está activo |
| Artículos: editar | Todos, si está activo | Sólo propios, si está activo |
| Artículos: eliminar | Sí | No |

Un editor nunca puede eliminar artículos, incluso si es el autor. Un usuario que conserva un token luego de ser desactivado puede consultar lo que su rol permite, pero recibe `403 Forbidden` al intentar crear o editar artículos.

## API

Todos los endpoints CRUD requieren `Authorization: Bearer <TOKEN>`. Las colecciones usan paginación nativa de Laravel, con 15 elementos por defecto. `per_page` permite solicitar entre 1 y 100 elementos.

### Authentication

| Método | Ruta | Permiso | Códigos principales |
| --- | --- | --- | --- |
| `POST` | `/api/v1/auth/login` | Público; sólo usuarios activos obtienen token | `200`, `401`, `403`, `422` |
| `GET` | `/api/v1/auth/me` | Usuario autenticado | `200`, `401` |
| `POST` | `/api/v1/auth/logout` | Usuario autenticado | `204`, `401` |

### Users

| Método | Ruta | Permiso | Códigos principales |
| --- | --- | --- | --- |
| `GET` | `/api/v1/users` | Admin | `200`, `401`, `403` |
| `POST` | `/api/v1/users` | Admin | `201`, `401`, `403`, `422` |
| `GET` | `/api/v1/users/{user}` | Admin | `200`, `401`, `403`, `404` |
| `PUT/PATCH` | `/api/v1/users/{user}` | Admin | `200`, `401`, `403`, `404`, `422` |
| `DELETE` | `/api/v1/users/{user}` | Admin | `204`, `401`, `403`, `404`, `409` |

Un usuario con artículos no puede eliminarse. La API responde `409 Conflict`; si puede eliminarse, sus tokens Sanctum también se eliminan.

### Categories

| Método | Ruta | Permiso | Códigos principales |
| --- | --- | --- | --- |
| `GET` | `/api/v1/categories` | Admin o editor | `200`, `401` |
| `POST` | `/api/v1/categories` | Admin | `201`, `401`, `403`, `422` |
| `GET` | `/api/v1/categories/{category}` | Admin o editor | `200`, `401`, `404` |
| `PUT/PATCH` | `/api/v1/categories/{category}` | Admin | `200`, `401`, `403`, `404`, `422` |
| `DELETE` | `/api/v1/categories/{category}` | Admin y sin artículos asociados | `204`, `401`, `403`, `404`, `409` |

### Articles

| Método | Ruta | Permiso | Códigos principales |
| --- | --- | --- | --- |
| `GET` | `/api/v1/articles` | Admin: todos; editor: sólo propios | `200`, `401` |
| `POST` | `/api/v1/articles` | Admin o editor activo | `201`, `401`, `403`, `422` |
| `GET` | `/api/v1/articles/{article}` | Admin o editor autor | `200`, `401`, `403`, `404` |
| `PUT/PATCH` | `/api/v1/articles/{article}` | Admin activo o editor activo autor | `200`, `401`, `403`, `404`, `422` |
| `DELETE` | `/api/v1/articles/{article}` | Admin | `204`, `401`, `403`, `404` |

Los campos `author_id` y `slug` no forman parte del input admitido. `category_ids` es obligatorio al crear y, cuando se envía al editar, debe contener al menos un ID único y existente.

## Ejemplos curl

### Crear una categoría como admin

```bash
curl --request POST http://localhost:8000/api/v1/categories \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'Authorization: Bearer <TOKEN>' \
  --data '{"name":"Laravel","description":"Contenido sobre Laravel.","status":"active"}'
```

### Crear un artículo

```bash
curl --request POST http://localhost:8000/api/v1/articles \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'Authorization: Bearer <TOKEN>' \
  --data '{"title":"Introducción a Laravel 12","content":"Contenido...","status":"draft","published_at":null,"category_ids":[1]}'
```

### Listar artículos

```bash
curl 'http://localhost:8000/api/v1/articles?per_page=15' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'Authorization: Bearer <TOKEN>'
```

### Editar un artículo

```bash
curl --request PATCH http://localhost:8000/api/v1/articles/1 \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'Authorization: Bearer <TOKEN>' \
  --data '{"title":"Guía práctica de Laravel 12","status":"published"}'
```

### Eliminar un artículo como admin

```bash
curl --request DELETE http://localhost:8000/api/v1/articles/1 \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'Authorization: Bearer <TOKEN>'
```

## Patrón de diseño

`ArticleObserver` aplica Observer Pattern mediante los eventos Eloquent `creating` y `updating`. Mantiene las invariantes derivadas del artículo —slug automático y único, y consistencia entre `status` y `published_at`— en un único punto, independientemente del controller o de otro flujo que persista el modelo.

## Respuestas de error

La API usa `401` para falta de autenticación, `403` para permisos insuficientes, `404` para recursos inexistentes, `422` para validación y `409` cuando la integridad funcional impide eliminar un usuario o categoría. Los errores son JSON y no exponen SQL, constraints ni trazas internas.

`GET /up` es el único endpoint técnico fuera de `/api/v1` y no requiere autenticación.

## Docker

```bash
docker compose up -d --build
docker compose ps
docker compose logs -f app
docker compose restart app
docker compose down
```

Abrir una shell:

```bash
docker compose exec app bash
```

Reconstruir el servicio cuando cambien `Dockerfile`, extensiones PHP o `composer.lock`:

```bash
docker compose build app
docker compose up -d app
```

## Artisan

```bash
docker compose exec app php artisan about
docker compose exec app php artisan route:list
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:status
docker compose exec app php artisan db:seed
```

## Testing

La suite usa PHPUnit y SQLite en memoria con foreign keys habilitadas:

```bash
docker compose exec app composer test
docker compose exec app php artisan test
docker compose exec app php artisan test --filter=NombreDelTest
```

## Calidad

```bash
docker compose exec app ./vendor/bin/pint --test
docker compose exec app composer validate --strict
docker compose exec app composer audit --no-interaction
```

No hay una herramienta de análisis estático configurada.

## Estructura

- `app/Enums`: roles y estados del dominio.
- `app/Http`: controllers, Form Requests y API Resources.
- `app/Models`: modelos y relaciones Eloquent.
- `bootstrap/`: arranque, middleware, rutas y excepciones.
- `database/`: migrations, factories, seeders y base SQLite local ignorada.
- `routes/api.php`: rutas funcionales bajo `/api/v1`.
- `tests/Feature`: comportamiento integrado del dominio y la API.
- `skills/`: instrucciones especializadas para agentes que trabajen en el proyecto.
