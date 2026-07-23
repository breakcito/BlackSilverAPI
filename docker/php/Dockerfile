# =============================================================================
# Stage 1: Vendor Builder (Instala dependencias en una capa aislada y congelada)
# =============================================================================
FROM php:8.4-cli-alpine AS vendor-builder

RUN apk add --no-cache curl git unzip

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

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

# $PHPIZE_DEPS garantiza que autoconf y los compiladores estén presentes durante todo el proceso
RUN apk add --no-cache curl netcat-openbsd $PHPIZE_DEPS

# Copiar la versión más reciente del instalador
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/

# Instala todas las extensiones (incluyendo redis) y elimina las herramientas de compilación al final
RUN install-php-extensions pdo_mysql intl zip bcmath pcntl sockets opcache redis && \
    apk del $PHPIZE_DEPS

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www

COPY --from=vendor-builder /var/www/vendor /var/www/vendor

COPY . /var/www

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative && \
    rm /usr/bin/composer

RUN mkdir -p /var/www/storage /var/www/bootstrap/cache && \
    chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

COPY --chown=www-data:www-data docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

EXPOSE 9000
CMD ["php-fpm"]