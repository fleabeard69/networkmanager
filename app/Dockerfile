FROM php:8.3-fpm-alpine

RUN apk add --no-cache libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
