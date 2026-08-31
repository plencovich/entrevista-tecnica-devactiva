---
name: laravel-testing
description: Diseñar, implementar y ejecutar tests PHPUnit para comportamiento Laravel, lógica aislada y regresiones en este repositorio.
---

# Laravel Testing

Usar PHPUnit 11, sin migrar a Pest. La suite configura SQLite en memoria, cache/sesión en memoria, cola síncrona y mailer `array` en `phpunit.xml`.

## Estrategia

- Preferir Feature Tests para rutas, middleware, validación, autorización, Eloquent, jobs y respuestas.
- En endpoints bajo `/api/v1`, afirmar también el contrato JSON y los status HTTP relevantes.
- Usar Unit Tests sólo cuando la unidad pueda probarse con valor real sin arrancar Laravel.
- Aplicar `RefreshDatabase` cuando el test dependa de migrations o escriba en base de datos.
- Crear datos con factories y estados expresivos; evitar fixtures compartidas y dependencias entre tests.
- Usar fakes nativos para mail, notifications, events, queues, storage y HTTP cuando el límite probado lo requiera.

Cubrir caso feliz, validaciones inválidas, permisos, recursos inexistentes, límites relevantes y regresiones. Afirmar resultados observables —status, response, base de datos, efectos— sin acoplarse a detalles internos innecesarios.

## Ejecución

```bash
docker compose exec app php artisan test
docker compose exec app php artisan test --filter=NombreDelTest
docker compose exec app composer test
```

Ejecutar primero el test focalizado durante la iteración y luego la suite completa antes de cerrar. No declarar éxito si un comando no fue ejecutado.
