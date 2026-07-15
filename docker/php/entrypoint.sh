#!/bin/sh
# =============================================================================
# Entrypoint del servicio php-fpm
# Se ejecuta UNA VEZ al arrancar el contenedor principal antes de iniciar FPM.
# Los contenedores reverb y queue NO usan este script.
# =============================================================================
set -e

echo "[entrypoint] Iniciando BlackSilverAPI..."

# Cachear configuración, rutas y eventos de Laravel para máximo rendimiento.
php artisan config:cache
php artisan route:cache
php artisan event:cache

# Crear el symlink de storage (idempotente, ignora si ya existe).
php artisan storage:link 2>/dev/null || true

echo "[entrypoint] Arranque completado. Iniciando PHP-FPM..."

# Reemplazar el proceso del script con php-fpm para que Docker gestione el PID 1.
exec "$@"
