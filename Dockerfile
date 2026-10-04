FROM composer:2@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS composer

FROM php:8.5-fpm-bookworm@sha256:53eab56a8f43f51a92119f6c29b3448af98eec288ff17f92829354a4b4c9ca05

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev \
    && docker-php-ext-install -j4 pdo_pgsql zip \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
ENV COMPOSER_HOME=/tmp/composer

CMD ["php-fpm"]
