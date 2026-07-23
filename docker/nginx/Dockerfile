FROM nginx:alpine

RUN apk add --no-cache curl

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY public /var/www/public

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]