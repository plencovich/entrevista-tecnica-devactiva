# CMS API

API REST modular construida con Laravel 12 y preparada para ejecutarse localmente con Docker y SQLite.

## Stack

- PHP 8.4.25.
- Laravel 12.68.0.
- Composer 2.10.2.
- SQLite 3.40.1.
- Docker y Docker Compose.
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
```

Durante el arranque, el contenedor `app` instala las dependencias Composer, crea `database/database.sqlite` si no existe, genera `APP_KEY` cuando está vacío y ejecuta las migrations pendientes.

La aplicación queda disponible en [http://localhost:8000](http://localhost:8000). El healthcheck está expuesto en [http://localhost:8000/up](http://localhost:8000/up).

## Configuración

`.env.example` contiene valores seguros para desarrollo. Antes de iniciar, revisar especialmente:

- `APP_PORT`: puerto HTTP publicado; por defecto `8000`.
- `HOST_UID` y `HOST_GID`: usuario y grupo del contenedor; por defecto `1000`.
- `APP_URL`: URL local de la aplicación.

La base SQLite persiste en `database/database.sqlite` y está excluida de Git. No guardar secretos reales en `.env.example` ni versionar `.env`.

Si se cambian `HOST_UID` o `HOST_GID`, reconstruir la imagen:

```bash
docker compose build --no-cache app
docker compose up -d
```

## Docker

Construir e iniciar:

```bash
docker compose up -d --build
```

Consultar el estado y el healthcheck:

```bash
docker compose ps
```

Ver logs:

```bash
docker compose logs -f
docker compose logs -f app
```

Detener, iniciar o reiniciar:

```bash
docker compose stop
docker compose start
docker compose restart
docker compose down
```

Abrir una shell:

```bash
docker compose exec app bash
```

Reconstruir el servicio cuando cambien `Dockerfile`, las extensiones PHP o `composer.lock`:

```bash
docker compose build app
docker compose up -d app
```

## API

La URL base local para los endpoints funcionales es:

```text
http://localhost:8000/api/v1
```

La versión actual sólo prepara el enrutamiento y no expone todavía recursos funcionales del CMS. `/up` se mantiene como healthcheck técnico fuera del prefijo versionado.

## Artisan

```bash
docker compose exec app php artisan about
docker compose exec app php artisan route:list
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

## Composer

```bash
docker compose exec app composer install
docker compose exec app composer require vendor/package
docker compose exec app composer validate --strict
docker compose exec app composer audit --no-interaction
```

Después de modificar `composer.lock`, reconstruir `app` para que una instalación nueva incluya las mismas dependencias.

## Testing

La suite usa PHPUnit y SQLite en memoria:

```bash
docker compose exec app composer test
docker compose exec app php artisan test
docker compose exec app php artisan test --filter=NombreDelTest
```

## Calidad

Laravel Pint verifica y formatea el código PHP:

```bash
docker compose exec app ./vendor/bin/pint --test
docker compose exec app ./vendor/bin/pint
```

No hay una herramienta de análisis estático configurada.

## Estructura

- `app/`: código de aplicación, modelos, controllers y providers.
- `bootstrap/`: arranque y configuración de rutas y excepciones.
- `config/`: configuración consumida por Laravel.
- `database/`: migrations, factories, seeders y base SQLite local ignorada.
- `routes/api.php`: rutas funcionales bajo `/api/v1`.
- `routes/console.php`: comandos de consola.
- `tests/Feature`: pruebas del comportamiento integrado de la API.
- `tests/Unit`: pruebas de lógica PHP aislada cuando aporten valor.
- `docker/`: scripts de inicio del contenedor.
- `skills/`: instrucciones especializadas para agentes que trabajen en el proyecto.
