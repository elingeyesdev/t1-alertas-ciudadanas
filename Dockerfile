# syntax=docker/dockerfile:1
FROM composer:2.8 AS composer-bin

FROM php:8.5-apache AS php-runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
      libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libicu-dev \
      libzip-dev libpq-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j2 pdo_pgsql pgsql mbstring gd intl zip bcmath \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer

FROM php-runtime AS composer-deps
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress --optimize-autoloader

FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY backend/package.json backend/package-lock.json ./
RUN npm ci
COPY backend/ ./
RUN npm run build

FROM php-runtime AS production
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php-production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/entrypoint.sh /usr/local/bin/alertas-entrypoint
RUN chmod 0755 /usr/local/bin/alertas-entrypoint
WORKDIR /var/www/html
COPY --chown=www-data:www-data backend/ ./
COPY --from=composer-deps --chown=www-data:www-data /app/vendor/ ./vendor/
COPY --from=assets --chown=www-data:www-data /app/public/build/ ./public/build/
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache \
    && ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage \
    && chown -R www-data:www-data storage bootstrap/cache \
    && su -s /bin/sh www-data -c 'php artisan package:discover --ansi'
ENTRYPOINT ["docker-php-entrypoint", "/usr/local/bin/alertas-entrypoint"]
EXPOSE 80
CMD ["apache2-foreground"]

FROM production AS local
COPY docker/php-local.ini /usr/local/etc/php/conf.d/zzz-local.ini
RUN composer install --no-scripts --no-interaction --prefer-dist --no-progress
