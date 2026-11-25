FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       mariadb-server \
       mariadb-client \
       ca-certificates \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY docker/my-lowmem.cnf /etc/mysql/mariadb.conf.d/99-wms.cnf
COPY docker/mpm_prefork.conf /etc/apache2/mods-available/mpm_prefork.conf
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
COPY . /var/www/html

RUN mkdir -p /var/www/html/backend/logs \
    && chown -R www-data:www-data /var/www/html/backend/logs

ENV PORT=10000
EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
