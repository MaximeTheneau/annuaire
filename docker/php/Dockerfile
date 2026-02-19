FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
      bash \
      icu-dev \
      libzip-dev \
    && docker-php-ext-install -j$(nproc) \
      intl \
      pdo_mysql \
      zip \
    && apk del icu-dev libzip-dev

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /srv/app
