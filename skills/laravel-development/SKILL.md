---
name: laravel-development
description: Implementar o revisar funcionalidad de aplicación en este repositorio Laravel 12, respetando su estructura nativa y evitando sobrearquitectura.
---

# Laravel Development

Usar esta skill para cambios funcionales PHP/Laravel. Leer primero `AGENTS.md` y limitar el trabajo al comportamiento solicitado.

La aplicación es API-only: registrar endpoints en `routes/api.php`, donde Laravel aplica el prefijo `/api/v1`, y responder mediante JSON. No introducir vistas ni herramientas de interfaz web.

## Flujo

1. Inspeccionar rutas, modelos, migrations, configuración y tests relacionados antes de diseñar la solución.
2. Identificar validación, autorización, persistencia, respuesta y efectos secundarios del caso de uso.
3. Elegir la menor cantidad de piezas Laravel que mantenga responsabilidades claras.
4. Implementar tipos y nombres explícitos siguiendo el estilo existente.
5. Ejecutar tests focalizados, suite relevante y Pint.

## Decisiones de diseño

- Dejar en el controller sólo coordinación HTTP. Extraer lógica cuando exista una responsabilidad concreta, no por cantidad de líneas.
- Usar Form Requests para validación/autorización no trivial y Policies para acceso a recursos.
- Mantener reglas e invariantes cercanas al modelo cuando le pertenezcan; usar un service o action para operaciones de negocio que coordinan varios colaboradores.
- Usar API Resources únicamente para respuestas JSON que necesiten contrato estable.
- Despachar Jobs sólo para trabajo realmente asíncrono, lento o reintentable. Introducir Events cuando varios consumidores independientes justifiquen el desacoplamiento.
- Preferir excepciones y respuestas nativas de Laravel; no envolver errores sin aportar semántica.

## Seguridad y configuración

- Validar y autorizar antes de leer o escribir recursos controlados por el usuario.
- Usar Eloquent o bindings para datos externos y controlar cualquier contenido de usuario incluido en respuestas JSON.
- Controlar mass assignment, serialización y datos enviados a logs.
- Acceder a valores de entorno mediante `config()`. Agregar la clave correspondiente en `config/` y un placeholder seguro en `.env.example`.
- No registrar payloads, tokens o credenciales sensibles.

Evitar repositories genéricos, interfaces especulativas, DTOs ceremoniales, traits triviales y services que sólo delegan una llamada.
