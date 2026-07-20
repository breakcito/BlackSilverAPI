FROM nginx:alpine

# curl es requerido por Docker HEALTHCHECK (Coolify/Traefik).
RUN apk add --no-cache curl

# nginx.conf (main): worker_processes, events, http block.
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf

# default.conf.template → procesado por envsubst al iniciar el contenedor.
# La imagen nginx:alpine corre automáticamente envsubst sobre cualquier
# archivo en /etc/nginx/templates/ y deposita el resultado en
# /etc/nginx/conf.d/<nombre sin .template>.
# Variable requerida: SERVER_NAME (ej. api.blacksilver.pe / api-dev.blacksilver.pe)
COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template

COPY public /var/www/public
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
