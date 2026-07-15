# =============================================================================
# Stage 1: Builder
# Instala extensiones PHP, Composer y dependencias de la aplicación.
# =============================================================================
FROM php:8.4-cli-alpine AS builder

# Instalar dependencias del sistema indispensables para Composer
RUN apk add --no-cache curl git unzip

# Descargar e instalar el instalador de extensiones PHP
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions

# Instalar extensiones PHP requeridas usando binarios precompilados de Alpine
RUN install-php-extensions pdo_mysql intl zip bcmath pcntl sockets redis opcache

WORKDIR /var/www

# Copiar Composer desde la imagen oficial para evitar descargas manuales
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Copiar archivos de dependencias de Composer para aprovechar la caché de Docker
COPY composer.json composer.lock /var/www/

# Instalar dependencias de producción (omite scripts y autoloader temporalmente)
RUN composer install \
    --no-dev \
    --no-autoloader \
    --no-scripts \
    --no-interaction \
    --no-progress \
    --prefer-dist

# Copiar el resto del código de la aplicación
COPY . /var/www

# Generar el autoloader optimizado
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && rm -rf /root/.composer

# =============================================================================
# Stage 2: Producción
# Imagen final ligera con PHP-FPM para servir la API.
# =============================================================================
FROM php:8.4-fpm-alpine AS production

# Instalar dependencias de runtime mínimas y netcat para healthcheck
RUN apk add --no-cache \
    icu-libs \
    libzip \
    netcat-openbsd

# Copiar extensiones PHP y configuraciones desde el builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/    /usr/local/etc/php/conf.d/
COPY --from=builder /usr/local/bin/docker-php-ext-* /usr/local/bin/

# Usar la configuración de producción de PHP para máxima seguridad y rendimiento
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Copiar el código fuente optimizado desde el builder
COPY --from=builder /var/www /var/www

WORKDIR /var/www

# Copiar el entrypoint de inicio
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Asegurar permisos de todo el directorio /var/www para www-data (necesario para storage:link y cachés)
RUN chown -R www-data:www-data /var/www

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
