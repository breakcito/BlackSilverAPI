# =============================================================================
# Stage 1: Vendor Builder (Instala dependencias en una capa aisala y congelada)
# =============================================================================
FROM php:8.4-cli-alpine AS vendor-builder

RUN apk add --no-cache curl git unzip

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copiar UNICAMENTE manifiestos para congelar la capa de caché
COPY composer.json composer.lock ./

RUN --mount=type=cache,target=/root/.composer \
    composer install \
    --no-dev \
    --no-autoloader \
    --no-scripts \
    --no-interaction \
    --no-progress \
    --prefer-dist

# =============================================================================
# Stage 2: Imagen Final de Producción
# =============================================================================
FROM php:8.4-fpm-alpine AS production

RUN apk add --no-cache curl netcat-openbsd

# Copiar instalador de extensiones desde imagen oficial (0 descargas externas por curl)
COPY --from=mlocati/php-extension-installer:2.7 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_mysql intl zip bcmath pcntl sockets opcache redis

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www

# 1. Copiar vendor pre-construido de Stage 1 (100% en caché si composer.lock no cambia)
COPY --from=vendor-builder /var/www/vendor /var/www/vendor

# 2. Copiar el código de la app (Solo esta capa se invalida al cambiar 1 línea)
COPY . /var/www

# 3. Optimizar el autoloader sobre el código de la app
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative && \
    rm /usr/bin/composer

# 4. Asignar permisos SOLO a carpetas de escritura (evita I/O masivo en vendor/)
RUN mkdir -p /var/www/storage /var/www/bootstrap/cache && \
    chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

COPY --chown=www-data:www-data docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

EXPOSE 9000
CMD ["php-fpm"]