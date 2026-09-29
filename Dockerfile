FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

FROM node:22-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

FROM php:8.3-fpm-bookworm AS base

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

# Packages follow the php:8.3-fpm-bookworm tag; pinning them here drifts from that base.
# hadolint ignore=DL3008
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libicu-dev \
        libonig-dev \
    && docker-php-ext-install pdo_mysql bcmath intl zip opcache pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* \
    && command -v runuser \
    && command -v flock

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/zz-www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod 755 /usr/local/bin/entrypoint.sh \
    && usermod -u 1000 www-data \
    && groupmod -g 1000 www-data \
    && mkdir -p /var/www/secrets \
    && chown www-data:www-data /var/www/secrets

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

FROM base AS production

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/98-opcache.ini
COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY --from=frontend --chown=www-data:www-data /app/public/build /var/www/html/public/build

# Root only for the entrypoint's volume chown. It re-executes as www-data
# before migrations, php-fpm, the queue worker, and the scheduler.
# hadolint ignore=DL3002
USER root

FROM nginx:1.27-alpine AS nginx

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend /app/public /var/www/html/public

FROM base AS development

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
