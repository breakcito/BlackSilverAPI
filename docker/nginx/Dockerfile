FROM nginx:alpine
# Copiamos como template a /etc/nginx/templates/ para que el entrypoint oficial
# de nginx:alpine aplique envsubst automáticamente y resuelva ${PHP_FPM_HOST}
# y ${REVERB_HOST} a los container_names únicos del env actual (php-fpm-prod/dev,
# reverb-prod/dev). Esto evita el round-robin de Docker DNS entre environments.
COPY docker/nginx/nginx.conf.template /etc/nginx/templates/default.conf.template
COPY public /var/www/public
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
