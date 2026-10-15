# Build frontend assets
FROM node:22-alpine AS assets

WORKDIR /app

COPY . .

RUN npm ci \
    && npm run build \
    && test -f public/build/manifest.json \
    && echo "Vite manifest generated successfully" \
    && ls -lah public/build


# PHP / Laravel application
FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libsqlite3-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_sqlite \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
        database \
    && touch database/database.sqlite \
    && php artisan storage:link --force \
    && chown -R www-data:www-data \
        database storage bootstrap/cache

COPY --from=assets /app/public/build /app/public/build

ENV APP_ENV=production \
    APP_DEBUG=false

EXPOSE 10000

USER www-data

CMD ["sh", "-c", "php artisan migrate --force && php artisan db:seed --force && php artisan config:cache && exec php artisan serve --host 0.0.0.0 --port ${PORT:-10000}"]
