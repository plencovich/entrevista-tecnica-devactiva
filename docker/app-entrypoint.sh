#!/bin/sh

set -eu

if [ ! -f .env ]; then
    echo "No se encontró .env. Copiá .env.example antes de iniciar el entorno." >&2
    exit 1
fi

composer install --no-interaction --prefer-dist --no-progress

touch database/database.sqlite

if ! grep -Eq '^APP_KEY=.+$' .env; then
    php artisan key:generate --force --no-interaction
fi

php artisan migrate --force --no-interaction

exec "$@"
