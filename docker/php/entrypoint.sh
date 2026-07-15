#!/bin/sh
# =============================================================================
# Entrypoint del servicio php-fpm
# Se ejecuta UNA VEZ al arrancar el contenedor principal antes de iniciar FPM.
# Los contenedores reverb y queue NO usan este script.
# =============================================================================
set -e

echo "[entrypoint] Iniciando BlackSilverAPI..."

# Esperar a que la base de datos esté disponible si se define DB_HOST
if [ -n "$DB_HOST" ]; then
    echo "[entrypoint] Esperando a la base de datos ($DB_HOST:${DB_PORT:-3306})..."
    until nc -z -w 2 "$DB_HOST" "${DB_PORT:-3306}"; do
        echo "[entrypoint] Base de datos no disponible, reintentando en 2 segundos..."
        sleep 2
    done
    echo "[entrypoint] Base de datos conectada con éxito."
fi

# Ejecutar migraciones automáticamente en producción
echo "[entrypoint] Ejecutando migraciones de base de datos..."
php artisan migrate --force

# Cachear configuración, rutas y eventos de Laravel para máximo rendimiento.
php artisan config:cache
php artisan route:cache
php artisan event:cache

# Crear el symlink de storage (idempotente, ignora si ya existe).
php artisan storage:link 2>/dev/null || true

echo "[entrypoint] Arranque completado. Iniciando PHP-FPM..."

# Reemplazar el proceso del script con php-fpm para que Docker gestione el PID 1.
exec "$@"
