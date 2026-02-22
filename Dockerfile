FROM php:8.4-cli

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    libpng-dev libonig-dev libxml2-dev zip curl unzip git libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copiamos el código
COPY . .

# Instalamos dependencias
RUN composer install --no-interaction --no-plugins --no-scripts --prefer-dist

# Permisos
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache

# Usamos el puerto 9095 - para que no choque con otros servicios
EXPOSE 9095

# Ejecutamos el servidor en el nuevo puerto
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=9095"]