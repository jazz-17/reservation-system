# ============================================
# Base stage: PHP 8.4 FPM with extensions
# ============================================
FROM composer:2@sha256:f0809732b2188154b3faa8e44ab900595acb0b09cd0aa6c34e798efe4ebc9021 AS composer

FROM php:8.4-fpm-bookworm@sha256:042c9b8a54d3b1a1e128eaafaf7cdcea7673e86483cfaa590620a5ea8509aad4 AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libicu-dev \
    libfontconfig1 \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        zip \
        gd \
        intl \
        pcntl \
        bcmath \
        opcache \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN groupadd -g 1000 www && useradd -u 1000 -g www -m www

# ============================================
# PHP build deps stage: tools for Composer fallbacks
# ============================================
FROM base AS php-build

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ============================================
# Vendor stage: install PHP dependencies
# ============================================
FROM php-build AS vendor

COPY composer.json composer.lock ./
ENV COMPOSER_MAX_PARALLEL_HTTP=4
RUN apt-get update && apt-get install -y --no-install-recommends \
        openssh-client \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
RUN set -eux; \
    for attempt in 1 2 3 4 5; do \
        composer install --no-dev --no-scripts --no-interaction --no-progress --optimize-autoloader && exit 0; \
        echo "Composer install failed (attempt ${attempt}/5), retrying..."; \
        sleep $((attempt * 5)); \
    done; \
    exit 1

# ============================================
# Wayfinder stage: generate TypeScript types
# ============================================
FROM vendor AS wayfinder

COPY . .
RUN mkdir -p \
        storage/app \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/pail \
        bootstrap/cache
RUN composer dump-autoload --optimize \
    && php artisan wayfinder:generate --with-form

# ============================================
# Node build stage (frontend assets)
# ============================================
FROM node:22-slim@sha256:5373f1906319b3a1f291da5d102f4ce5c77ccbe29eb637f072b6c7b70443fc36 AS node-build

WORKDIR /build

COPY package.json package-lock.json ./
ENV npm_config_audit=false \
    npm_config_fund=false \
    npm_config_fetch_retries=5 \
    npm_config_fetch_retry_factor=2 \
    npm_config_fetch_retry_mintimeout=10000 \
    npm_config_fetch_retry_maxtimeout=120000
RUN set -eux; \
    for attempt in 1 2 3 4 5; do \
        npm ci && exit 0; \
        echo "npm ci failed (attempt ${attempt}/5), retrying..."; \
        sleep $((attempt * 5)); \
    done; \
    exit 1

COPY . .
COPY --from=wayfinder /var/www/html/resources/js/actions resources/js/actions
COPY --from=wayfinder /var/www/html/resources/js/routes resources/js/routes
COPY --from=wayfinder /var/www/html/resources/js/wayfinder resources/js/wayfinder

ENV VITE_SKIP_WAYFINDER=1
RUN npm run build

# ============================================
# Production
# ============================================
FROM base AS prod

COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/zz-prod.ini

COPY composer.json composer.lock ./
COPY --from=vendor /var/www/html/vendor /var/www/html/vendor

COPY . .

COPY --from=node-build /build/public/build public/build

RUN mkdir -p \
        storage/app \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/pail \
        bootstrap/cache
RUN composer dump-autoload --optimize \
    && php artisan view:cache

RUN chown -R www:www storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ============================================
# Nginx stage (serves static files + proxies PHP)
# ============================================
FROM nginx:alpine@sha256:1d13701a5f9f3fb01aaa88cef2344d65b6b5bf6b7d9fa4cf0dca557a8d7702ba AS nginx

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=prod /var/www/html/public /var/www/html/public
