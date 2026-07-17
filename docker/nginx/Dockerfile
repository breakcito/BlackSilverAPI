FROM nginx:alpine
# nginx.conf es estático (no tiene vars): contiene el bloque http que incluye
# los archivos de /etc/nginx/conf.d/*.conf. Ahí es donde va el server block.
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf

# default.conf.template se procesa con envsubst en el entrypoint oficial de
# nginx:alpine. Las variables ${PHP_FPM_HOST} y ${REVERB_HOST} se resuelven a
# los container_names únicos del env actual (php-fpm-prod/dev, reverb-prod/dev)
# gracias a las env vars definidas en docker-compose.yml. Esto evita el
# round-robin de Docker DNS entre environments en la red compartida de Coolify.
COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template

COPY public /var/www/public
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
