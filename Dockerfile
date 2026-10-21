FROM composer:2@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS composer

FROM php:8.5.12-fpm-bookworm@sha256:740a5adab5af19504d8aa0754e903e20b40b0153cfd4013c11e4df2029a7b73c AS vendor
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev libonig-dev \
    && docker-php-ext-install -j4 mbstring pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --optimize-autoloader

FROM php:8.5.12-fpm-bookworm@sha256:740a5adab5af19504d8aa0754e903e20b40b0153cfd4013c11e4df2029a7b73c AS runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx supervisor libpq-dev libzip-dev libonig-dev curl \
    && docker-php-ext-install -j4 mbstring pdo_pgsql zip opcache \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && apt-get purge -y libpq-dev libzip-dev libonig-dev \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/* \
    && groupadd --system --gid 10001 app \
    && useradd --system --uid 10001 --gid app --home-dir /var/www/html --no-create-home app \
    && mkdir -p /run/php /var/lib/nginx /var/log/nginx /tmp/client_body /tmp/fastcgi /tmp/proxy /tmp/uwsgi /tmp/scgi /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R app:app /var/www/html /var/lib/nginx /var/log/nginx /run/php /tmp/client_body /tmp/fastcgi /tmp/proxy /tmp/uwsgi /tmp/scgi

WORKDIR /var/www/html
COPY --chown=app:app . .
COPY --from=vendor --chown=app:app /app/vendor ./vendor
COPY docker/production/nginx.conf /etc/nginx/nginx.conf
COPY docker/production/supervisord.conf /etc/supervisor/conf.d/app.conf
COPY docker/production/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/production/php.ini /usr/local/etc/php/conf.d/zz-app.ini
RUN php artisan package:discover --ansi \
    && chown -R app:app storage bootstrap/cache

ENV APP_ENV=production APP_DEBUG=false
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl --fail --silent http://127.0.0.1:8080/api/v1/health || exit 1

USER app
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/app.conf", "-n"]
