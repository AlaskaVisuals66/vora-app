# syntax=docker/dockerfile:1.7

# ---- Stage 1: build front-end assets with Vite ----
FROM node:20-alpine AS assets
WORKDIR /app

# Vite bakes these into the JS bundle at build time
ARG VITE_PUSHER_APP_KEY
ARG VITE_PUSHER_HOST
ARG VITE_PUSHER_PORT=443
ARG VITE_PUSHER_SCHEME=https
ARG VITE_PUSHER_APP_CLUSTER=mt1
ENV VITE_PUSHER_APP_KEY=$VITE_PUSHER_APP_KEY \
    VITE_PUSHER_HOST=$VITE_PUSHER_HOST \
    VITE_PUSHER_PORT=$VITE_PUSHER_PORT \
    VITE_PUSHER_SCHEME=$VITE_PUSHER_SCHEME \
    VITE_PUSHER_APP_CLUSTER=$VITE_PUSHER_APP_CLUSTER

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY postcss.config.js tailwind.config.js vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build


# ---- Stage 2: install PHP dependencies with Composer ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --prefer-dist \
        --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-scripts


# ---- Stage 3: production runtime (php-fpm + nginx + horizon via supervisord) ----
FROM php:8.3-fpm-alpine AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false \
    PHP_OPCACHE_ENABLE=1 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    PHP_MEMORY_LIMIT=512M

RUN apk add --no-cache \
        nginx \
        supervisor \
        bash \
        curl \
        tini \
        sqlite \
        sqlite-dev \
        sqlite-libs \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        autoconf \
        g++ \
        make \
        linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_sqlite \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del --no-network autoconf g++ make linux-headers \
    && rm -rf /var/cache/apk/* /tmp/pear

WORKDIR /var/www/html

COPY --from=vendor   /app                   /var/www/html
COPY --from=assets   /app/public/build      /var/www/html/public/build

COPY docker/nginx.conf       /etc/nginx/nginx.conf
COPY docker/php.ini          /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php-fpm.conf     /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh    /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p \
        /var/www/html/storage/app/public \
        /var/www/html/storage/framework/cache/data \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/framework/testing \
        /var/www/html/storage/logs \
        /var/www/html/bootstrap/cache \
        /run/nginx /var/log/supervisor \
    && chown -R www-data:www-data /var/www/html /run/nginx /var/log/supervisor

EXPOSE 8080

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint.sh"]
CMD ["web"]
