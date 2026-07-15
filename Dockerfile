# ---------- STAGE 1: Build de dependencias ----------
FROM composer:2.7 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    --no-interaction \
    --no-progress \
    --no-plugins

# ---------- STAGE 2: Runtime (Con Apache) ----------
FROM php:8.3-apache AS runtime

LABEL maintainer="BlackSilver <dev@blacksilver.pe>"
LABEL description="BlackSilver API - Laravel 12 Apache production image"

ARG APP_ENV=production
ENV APP_ENV=${APP_ENV}

# Ajustar el DocumentRoot de Apache directamente a la carpeta /public de Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Habilitar el módulo rewrite de Apache para que funcione el .htaccess nativo de Laravel
RUN a2enmod rewrite headers

# Instalar dependencias esenciales de Debian
RUN apt-get update && apt-get install -y --no-install-recommends \
        bash \
        curl \
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
        libfreetype6-dev \
        libzip-dev \
        libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# Configurar zona horaria
ENV TZ=America/Lima
RUN ln -snf /usr/share/zoneinfo/${TZ} /etc/localtime && echo ${TZ} > /etc/timezone

# Instalar extensiones de PHP necesarias para Laravel y optimizaciones
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        intl \
        gd \
        zip \
        bcmath \
        opcache \
        exif

WORKDIR /var/www/html

# Copiar el código de la aplicación y dependencias de Composer
COPY . /var/www/html
COPY --from=vendor /app/vendor /var/www/html/vendor

# Permisos para que Apache pueda escribir en cache y logs
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://127.0.0.1/up || exit 1

# Arranca directamente Apache en primer plano de manera nativa sin entrypoint
CMD ["apache2-foreground"]