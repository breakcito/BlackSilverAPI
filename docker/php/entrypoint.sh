#!/bin/sh
# =============================================================================
# Entrypoint del servicio php-fpm
# Se ejecuta UNA VEZ al arrancar el contenedor principal antes de iniciar FPM.
# Los contenedores reverb y queue NO usan este script.
#
# NOTA: Este proyecto NO usa migraciones automáticas.
# La base de datos se gestiona de forma independiente por el equipo.
# =============================================================================
set -e

echo "[entrypoint] Iniciando BlackSilverAPI (APP_ENV=${APP_ENV:-local})..."

# Esperar a que la base de datos esté disponible si se define DB_HOST
if [ -n "$DB_HOST" ]; then
    echo "[entrypoint] Esperando a la base de datos ($DB_HOST:${DB_PORT:-3306})..."
    until nc -z -w 2 "$DB_HOST" "${DB_PORT:-3306}"; do
        echo "[entrypoint] Base de datos no disponible, reintentando en 2 segundos..."
        sleep 2
    done
    echo "[entrypoint] Base de datos conectada con éxito."
fi

# Cachear configuración, rutas y eventos SOLO en producción.
# En dev se omite para no romper cachés en cada restart y permitir ver cambios al vuelo.
if [ "$APP_ENV" = "production" ]; then
    echo "[entrypoint] Cacheando configuración para producción..."
    php artisan config:cache
    php artisan route:cache
    php artisan event:cache
else
    echo "[entrypoint] APP_ENV=$APP_ENV — se omiten los caches de Laravel."
fi

# Crear el symlink de storage (idempotente, ignora si ya existe).
php artisan storage:link 2>/dev/null || true

echo "[entrypoint] Arranque completado. Iniciando PHP-FPM..."

# Reemplazar el proceso del script con php-fpm para que Docker gestione el PID 1.
exec "$@"
