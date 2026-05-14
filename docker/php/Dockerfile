FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        git curl unzip icu-dev libzip-dev oniguruma-dev libpng-dev \
        postgresql-dev libxml2-dev autoconf g++ make linux-headers \
    && docker-php-ext-install pdo_pgsql pgsql mbstring zip bcmath intl exif pcntl gd sockets opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del autoconf g++ make

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html

RUN addgroup -g 1000 app && adduser -G app -u 1000 -D -s /bin/sh app \
    && chown -R app:app /var/www/html

USER app

EXPOSE 9000
CMD ["php-fpm"]
