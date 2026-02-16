FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx supervisor
RUN docker-php-ext-install pdo pdo_mysql

COPY docker/nginx-web.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord-web.conf /etc/supervisord.conf

WORKDIR /var/www/html

COPY web /var/www/html/web
COPY src /var/www/html/src

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
