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

## Endpoints

| Método | Ruta | Autenticación | Respuesta |
| --- | --- | --- | --- |
| `POST` | `/api/v1/auth/login` | Pública | `200`, `401`, `403` o `422` |
| `GET` | `/api/v1/auth/me` | Bearer Token | `200` o `401` |
| `POST` | `/api/v1/auth/logout` | Bearer Token | `204` o `401` |
| `GET` | `/up` | Pública | Healthcheck técnico |

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
