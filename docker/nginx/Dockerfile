FROM nginx:alpine

RUN apk add --no-cache curl

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY public /var/www/public

EXPOSE 80

# Registra el healthcheck en los metadatos de la imagen para Coolify / Traefik
HEALTHCHECK --interval=5s --timeout=2s --retries=3 --start-period=5s \
  CMD curl -sf http://127.0.0.1/api/health/live || exit 1

CMD ["nginx", "-g", "daemon off;"]