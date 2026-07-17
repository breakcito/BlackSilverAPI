FROM nginx:alpine
# nginx.conf (main): contiene worker_processes, events, http con include
# de /etc/nginx/conf.d/*.conf.
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf

# default.conf (server block): incluye la lógica de proxy_pass y fastcgi_pass
# usando aliases de red (php-fpm-local, reverb-local) que son únicos por
# compose project. Esto evita que nginx haga round-robin hacia el container
# del environment opuesto en la red compartida de Coolify.
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

COPY public /var/www/public
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
