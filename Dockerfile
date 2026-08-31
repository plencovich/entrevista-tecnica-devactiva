FROM php:8.4-cli-bookworm AS php-extensions

RUN apt-get update \
    && apt-get install --no-install-recommends --yes \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" pcntl zip \
    && rm -rf /var/lib/apt/lists/*

FROM composer:2 AS composer

FROM php:8.4-cli-bookworm

ARG APP_USER_ID=1000
ARG APP_GROUP_ID=1000

RUN apt-get update \
    && apt-get install --no-install-recommends --yes \
        libzip4 \
        unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=php-extensions /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-extensions /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

RUN groupadd --gid "${APP_GROUP_ID}" app \
    && useradd --uid "${APP_USER_ID}" --gid app --create-home --shell /bin/bash app

WORKDIR /var/www/html

COPY docker/app-entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

COPY --chown=app:app . .

USER app

RUN composer install --no-interaction --prefer-dist --no-progress

EXPOSE 8000

ENTRYPOINT ["app-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
