FROM php:8.3-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libfreetype6-dev libicu-dev libjpeg62-turbo-dev \
        libonig-dev libpng-dev libwebp-dev libxml2-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath exif gd intl mbstring opcache pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY deploy/php.ini /usr/local/etc/php/conf.d/wendkonta.ini

WORKDIR /var/www/html
