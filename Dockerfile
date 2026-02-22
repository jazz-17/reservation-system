# ============================================
# Base stage: PHP 8.4 FPM with extensions
# ============================================
FROM php:8.4-fpm-bookworm AS base

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
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN groupadd -g 1000 www && useradd -u 1000 -g www -m www

# ============================================
# Wayfinder stage: generate TypeScript types
# ============================================
FROM base AS wayfinder

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader

COPY . .
RUN composer dump-autoload --optimize \
    && php artisan wayfinder:generate --with-form

# ============================================
# Node build stage (frontend assets)
# ============================================
FROM node:22-slim AS node-build

WORKDIR /build

COPY package.json package-lock.json ./
RUN npm ci

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
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader

COPY . .

COPY --from=node-build /build/public/build public/build

RUN composer dump-autoload --optimize \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

RUN chown -R www:www storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

USER www

EXPOSE 9000
CMD ["php-fpm"]
