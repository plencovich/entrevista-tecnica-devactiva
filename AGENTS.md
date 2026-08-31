# Guía de trabajo del proyecto

## Stack

- Laravel 12 (`laravel/framework ^12.0`; lock actual 12.68.0).
- PHP `^8.2`; el entorno Docker usa PHP 8.4 CLI.
- Composer 2 dentro de la imagen de aplicación.
- SQLite para desarrollo y SQLite en memoria para tests.
- PHPUnit 11 para tests Feature y Unit.
- Docker Compose con un único servicio `app`.
- Laravel Pint como formatter y verificador de estilo PHP. No hay análisis estático configurado.

## Arquitectura

El proyecto es exclusivamente una API REST para un CMS modular. No existe interfaz web. Las rutas funcionales viven en `routes/api.php` y Laravel aplica globalmente el prefijo `/api/v1`. El código de aplicación se ubica en `app/`, las migrations, factories y seeders en `database/`, y los tests en `tests/Feature` y `tests/Unit`.

- Controllers: coordinan request, autorización, casos de uso y response; deben permanecer pequeños.
- Models: representan entidades Eloquent, relaciones, casts, scopes y reglas cercanas al modelo. Proteger mass assignment con `$fillable` o `$guarded` explícito.
- Form Requests: validan y autorizan todo input funcional que exceda un caso trivial.
- API Resources: definen contratos JSON estables y controlan la serialización cuando corresponda; no devolver modelos sin revisar los atributos expuestos.
- Services: sólo para una responsabilidad de negocio concreta que no pertenezca naturalmente a un modelo, action o controller. No crear capas vacías.
- Jobs: para trabajo asíncrono, reintentable o costoso; definir idempotencia, timeouts y manejo de fallos cuando corresponda.
- Events/Listeners: sólo cuando exista desacoplamiento real entre una acción y sus reacciones.
- Policies/Gates: centralizan autorización sobre recursos.
- Excepciones: usar excepciones de dominio o HTTP únicamente cuando mejoren el manejo del error; devolver errores HTTP como JSON sin exponer detalles internos.
- Tests: comportamiento de aplicación en `tests/Feature`; lógica PHP realmente aislada en `tests/Unit`.

Antes de una tarea especializada, leer la skill relevante en `skills/`: `laravel-development`, `laravel-testing`, `laravel-database`, `laravel-api` o `docker-development`. Las reglas globales de este archivo prevalecen y las skills no amplían el alcance autorizado.

## Reglas de implementación

- Seguir las convenciones nativas de Laravel 12, PSR-12 y los patrones ya presentes.
- No introducir frontend, vistas, Vite, Tailwind CSS, Node.js ni npm salvo un requerimiento futuro explícito.
- Versionar todas las rutas funcionales bajo `/api/v1`; mantener `/up` únicamente como healthcheck técnico.
- Preferir código simple, explícito y con tipos, type hints y return types adecuados.
- Mantener controllers pequeños y usar dependency injection cuando una dependencia real lo justifique.
- Validar todo input externo con Form Requests cuando corresponda y aplicar autorización antes de acceder o mutar recursos.
- Usar API Resources para contratos JSON que requieran una representación estable.
- Usar Eloquent directamente salvo que una abstracción resuelva una necesidad concreta.
- No crear repositories genéricos sobre Eloquent, interfaces de una sola implementación, DTOs, traits o services sin valor concreto.
- Evitar duplicación significativa, pero no introducir abstracciones especulativas.
- Usar transacciones cuando varias escrituras deban confirmarse o revertirse como una sola operación.
- Evitar N+1 mediante eager loading explícito y seleccionar sólo los datos necesarios en consultas sensibles al volumen.
- Mantener migrations reversibles cuando sea razonable y expresar constraints, foreign keys e índices que protejan invariantes reales.
- No exponer secretos, credenciales, tokens, hashes, datos sensibles ni detalles internos de excepciones.
- No hardcodear secretos. Leer variables de entorno sólo desde archivos de configuración; no usar `env()` en lógica de aplicación.
- Revisar validación, autorización, SQL injection, uploads, acceso por ID y mass assignment en toda superficie afectada.
- No modificar comportamiento funcional ajeno al alcance de la tarea.

## Patrón de diseño

El patrón exigido por el challenge se elegirá durante la implementación funcional según una necesidad concreta y deberá poder justificarse. No utilizar Repository automáticamente sobre Eloquent ni introducir interfaces con una única implementación únicamente para cumplir formalmente el requisito.

## Autenticación

La autenticación utiliza Laravel Sanctum mediante Bearer Tokens en los endpoints `/api/v1/auth/login`, `/api/v1/auth/me` y `/api/v1/auth/logout`. No instalar JWT ni configurar autenticación SPA/cookies.

## Reglas permanentes del CMS

- Centralizar en Policies el acceso administrativo, ownership y estado activo: el editor sólo consulta y edita artículos propios, nunca los elimina, y sólo usuarios activos crean o editan artículos.
- La autoría y el slug son internos; ningún request de artículo acepta `author_id` ni `slug`. Todo artículo creado por la API requiere una o más categorías.
- `ArticleObserver` mantiene el slug único y la consistencia entre `status` y `published_at`; no duplicar esas invariantes en controllers.
- Una categoría asociada o un usuario autor no se eliminan; anticipar la restricción y responder `409` sin depender del error SQL.

## Testing

Toda funcionalidad nueva debe evaluar Feature Tests, Unit Tests, integración y regresión. Favorecer Feature Tests para rutas, validación, autorización, persistencia y respuestas JSON. Usar Unit Tests sólo para lógica aislada que sea valioso probar sin framework.

Usar factories para datos relevantes y `RefreshDatabase` cuando un Feature Test escriba en base de datos. Cubrir el caso feliz, errores esperables, autorización, validaciones, edge cases y la regresión que motive un fix. Los tests deben ser deterministas y no depender de red, reloj o estado compartido sin control explícito.

## Calidad

Comandos disponibles en el entorno Docker:

```bash
docker compose exec app composer test
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
docker compose exec app ./vendor/bin/pint
```

No hay análisis estático configurado. No documentar ni asumir herramientas inexistentes.

## Git

- Codex no debe ejecutar `git commit` ni `git push`.
- Codex no debe crear tags, hacer merge o rebase, ni modificar la historia Git.
- Codex puede usar comandos Git de lectura como `git status`, `git diff` y `git log`.
- Al finalizar, Codex debe proponer commits lógicos con mensajes Conventional Commits en español e indicar los comandos `git add` exactos.
