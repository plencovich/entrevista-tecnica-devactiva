---
name: laravel-api
description: Implementar o revisar endpoints REST JSON, autenticación Sanctum y contratos HTTP versionados en esta API Laravel 12.
---

# Laravel API

Usar esta skill para trabajo sobre endpoints, autenticación, serialización o errores HTTP. Leer primero `AGENTS.md` y combinarla con las skills de desarrollo, base de datos o testing que correspondan.

## Contratos HTTP

- Registrar rutas funcionales en `routes/api.php`; el bootstrap agrega globalmente `/api/v1`.
- Validar input funcional con Form Requests y autorizar antes de acceder o mutar recursos.
- Usar API Resources para contratos estables y exponer únicamente campos públicos.
- Elegir códigos HTTP según el resultado: `200` para consultas/acciones con cuerpo, `201` al crear, `204` sin cuerpo, `401` sin autenticación válida, `403` sin permiso, `404` ausente y `422` para validación.
- Mantener respuestas JSON simples y consistentes. No introducir envoltorios o factories genéricas sin una necesidad concreta.
- Paginar colecciones potencialmente grandes y conservar metadatos/enlaces nativos de Laravel cuando sean útiles.

## Autenticación y errores

- Usar Sanctum con `Authorization: Bearer <token>` y `auth:sanctum`; no configurar JWT ni autenticación SPA/cookies.
- No exponer passwords, tokens persistidos, `remember_token`, trazas ni detalles internos.
- Mantener mensajes de credenciales inválidas genéricos para evitar enumeración de cuentas.
- Dejar que Laravel produzca errores JSON estándar salvo que el dominio necesite una semántica más clara.

Favorecer controllers pequeños, Eloquent directo, Form Requests, Resources y Policies. No crear repositories, DTOs, services o capas adicionales sólo para uniformar endpoints.
