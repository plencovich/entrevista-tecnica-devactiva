---
name: laravel-database
description: Diseñar migrations, modelos, factories, seeders y consultas Eloquent para la base SQLite actual del proyecto Laravel 12.
---

# Laravel Database

El desarrollo local usa `database/database.sqlite` y los tests SQLite en memoria. No cambiar de motor sin una decisión explícita del proyecto.

## Esquema y datos

- Expresar nulabilidad, defaults, unicidad, foreign keys, acciones de borrado e índices según invariantes y consultas reales.
- Mantener `up()` y `down()` coherentes y reversibles cuando sea seguro y razonable.
- No editar una migration ya compartida para alterar datos existentes; crear una nueva migration. En este repositorio sin releases, verificar igualmente el estado Git antes de decidir.
- Separar datos de prueba en factories y datos iniciales necesarios en seeders idempotentes cuando sea viable.
- No incluir datos sensibles o credenciales en seeders.

## Escrituras y consultas

- Proteger mass assignment y definir casts y relaciones con tipos/documentación útil.
- Usar transacciones para escrituras que forman una unidad atómica; mantener llamadas externas fuera de la transacción cuando sea posible.
- Evitar N+1 con `with()`, `load()` o agregados Eloquent, y paginar colecciones potencialmente grandes.
- Seleccionar columnas cuando reduzca volumen sin romper hidratación o relaciones.
- Usar Query Builder/Eloquent con bindings; justificar SQL raw y nunca interpolar input.

Validar con migrations frescas y tests relevantes:

```bash
docker compose exec app php artisan migrate:fresh
docker compose exec app php artisan test
```

`migrate:fresh` destruye los datos locales: ejecutarlo sólo cuando el alcance lo autorice o sobre una base descartable confirmada.
