FROM php:8.4-apache

# Extensiones necesarias para Laravel
RUN apt-get update && apt-get install -y \
    libpng-dev libonig-dev libxml2-dev libzip-dev zip unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar Composer copiándolo desde la imagen oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# php.ini
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && echo "memory_limit = 2048M"       >> "$PHP_INI_DIR/php.ini" \
 && echo "upload_max_filesize = 5000M" >> "$PHP_INI_DIR/php.ini" \
 && echo "post_max_size = 5000M"       >> "$PHP_INI_DIR/php.ini" \
 && echo "max_execution_time = 300"  >> "$PHP_INI_DIR/php.ini"

# Apache: mod_rewrite + DocumentRoot apuntando a /public
RUN a2enmod rewrite
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

CMD ["sh", "-c", "owner=$(stat -c '%u:%g' /var/www/html) && mkdir -p storage bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache && (if [ ! -f vendor/autoload.php ] || [ composer.json -nt vendor/autoload.php ] || [ composer.lock -nt vendor/autoload.php ]; then composer install --no-interaction --prefer-dist --optimize-autoloader && chown -R $owner vendor; fi) && php artisan storage:link --force && php artisan optimize:clear && php artisan optimize && apache2-foreground"]