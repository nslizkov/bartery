FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

RUN a2enmod rewrite

WORKDIR /var/www/html

RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

RUN echo '<Directory /var/www/html/public>' \
    '\nAllowOverride All' \
    '\nRequire all granted' \
    '\n</Directory>' \
    >> /etc/apache2/apache2.conf

EXPOSE 80
