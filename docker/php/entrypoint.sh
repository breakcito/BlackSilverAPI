#!/bin/sh
set -e

echo "[entrypoint] Iniciando BlackSilverAPI (APP_ENV=${APP_ENV:-local})..."

if [ -n "$DB_HOST" ]; then
    echo "[entrypoint] Esperando a la base de datos ($DB_HOST:${DB_PORT:-3306})..."
    until nc -z -w 2 "$DB_HOST" "${DB_PORT:-3306}"; do
        echo "[entrypoint] Base de datos no disponible, reintentando en 2 segundos..."
        sleep 2
    done
    echo "[entrypoint] Base de datos conectada con éxito."
fi

if [ "$APP_ENV" = "production" ]; then
    echo "[entrypoint] Cacheando configuración para producción..."
    php artisan config:cache
    php artisan route:cache
    php artisan event:cache
else
    echo "[entrypoint] APP_ENV=$APP_ENV — se omiten los caches de Laravel."
fi

# Valida symlink de forma segura
if [ ! -L /var/www/public/storage ]; then
    php artisan storage:link --force 2>/dev/null || true
fi

echo "[entrypoint] Arranque completado. Iniciando PHP-FPM..."

exec "$@"