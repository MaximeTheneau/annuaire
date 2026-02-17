FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
      icu-dev \
      libzip-dev \
    && docker-php-ext-install -j$(nproc) \
      intl \
      pdo_mysql \
      zip \
    && apk del icu-dev libzip-dev

WORKDIR /srv/app
