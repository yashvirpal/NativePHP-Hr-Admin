# Shared PHP/Composer image used by both backend/ and mobile/
FROM php:8.4-cli-alpine

COPY --from=ghcr.io/mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN apk add --no-cache git unzip nodejs npm \
    && install-php-extensions pdo_sqlite pdo_pgsql pdo_mysql bcmath intl zip pcntl gd

COPY php.ini /usr/local/etc/php/conf.d/zz-hr-dev.ini

WORKDIR /app
