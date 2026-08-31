---
name: docker-development
description: Construir, operar o diagnosticar el entorno Docker Compose local de esta API Laravel.
---

# Docker Development

El entorno API-only tiene un único servicio `app` con PHP 8.4, Composer y Laravel en el puerto 8000. El código se monta desde el host y `composer_vendor` conserva las dependencias PHP. SQLite vive en `database/database.sqlite` dentro del repositorio y está ignorado por Git.

## Operación

```bash
docker compose up -d --build
docker compose ps
docker compose logs -f app
docker compose exec app php artisan about
docker compose exec app composer install
docker compose down
```

El entrypoint de `app` exige `.env`, sincroniza las dependencias Composer con el lock, crea el archivo SQLite si falta, genera `APP_KEY` si está vacío y ejecuta migrations pendientes. No insertar secretos en imágenes ni en Compose.

## Cambios y diagnóstico

- Reconstruir `app` cuando cambien `Dockerfile`, extensiones PHP, `composer.lock` o scripts copiados a la imagen.
- Mantener el proceso como usuario no-root. Si hay problemas de permisos, alinear `HOST_UID` y `HOST_GID` en `.env` con el host y reconstruir.
- Revisar `docker compose config -q`, `docker compose ps`, el healthcheck `/up` y los logs antes de modificar infraestructura.
- No agregar servicios, extensiones, paquetes o volumes sin una necesidad observada.
- No incorporar herramientas o servicios de interfaz web salvo un requerimiento explícito.
- No borrar volumes ni `database/database.sqlite` durante diagnóstico sin autorización explícita.
