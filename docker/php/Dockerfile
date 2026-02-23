FROM php:8.4-fpm-alpine

# 1. Installation des dépendances de RUNTIME (celles qui restent)
# 2. Installation des dépendances de BUILD (celles qu'on supprime après)
RUN apk add --no-cache \
      bash \
      freetype \
      libjpeg-turbo \
      libpng \
      libwebp \
      libzip \
      icu-libs \
      && apk add --no-cache --virtual .build-deps \
      freetype-dev \
      icu-dev \
      libjpeg-turbo-dev \
      libpng-dev \
      libwebp-dev \
      libzip-dev \
  && docker-php-ext-configure gd \
      --with-freetype \
      --with-jpeg \
      --with-webp \
  && docker-php-ext-install -j$(nproc) \
      gd \
      intl \
      pdo_mysql \
      zip \
  && apk del .build-deps

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /srv/app
