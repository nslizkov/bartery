FROM httpd:2.4

# Включаем нужные модули
RUN sed -i '/#LoadModule proxy_module/s/^#//' /usr/local/apache2/conf/httpd.conf
RUN sed -i '/#LoadModule proxy_http_module/s/^#//' /usr/local/apache2/conf/httpd.conf

# Подключаем наш VirtualHost
COPY docker/proxy.conf /usr/local/apache2/conf/extra/proxy.conf
RUN echo "Include conf/extra/proxy.conf" >> /usr/local/apache2/conf/httpd.conf
