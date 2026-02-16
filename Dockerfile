FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx supervisor

# PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Nginx config (только API)
COPY docker/nginx-app.conf /etc/nginx/http.d/default.conf

# Supervisor to run nginx + php-fpm
COPY docker/supervisord.conf /etc/supervisord.conf

WORKDIR /var/www/html

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
