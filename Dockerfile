# syntax=docker/dockerfile:1

# ---------- Etapa 1: assets (Tailwind standalone + Alpine.js, sin Node) ----------
FROM debian:bookworm-slim AS assets
ARG TARGETARCH
ARG TAILWIND_VERSION=v4.3.4
ARG ALPINE_VERSION=3.17.5
RUN apt-get update && apt-get install -y --no-install-recommends curl ca-certificates && rm -rf /var/lib/apt/lists/*
RUN case "${TARGETARCH:-amd64}" in arm64) TW=linux-arm64 ;; *) TW=linux-x64 ;; esac \
 && curl -fsSL -o /usr/local/bin/tailwindcss "https://github.com/tailwindlabs/tailwindcss/releases/download/${TAILWIND_VERSION}/tailwindcss-${TW}" \
 && chmod +x /usr/local/bin/tailwindcss
WORKDIR /build
COPY resources ./resources
COPY templates ./templates
COPY src ./src
COPY public ./public
RUN tailwindcss -i resources/css/app.css -o public/assets/app.css --minify \
 && curl -fsSL -o public/assets/alpine.min.js "https://cdn.jsdelivr.net/npm/alpinejs@${ALPINE_VERSION}/dist/cdn.min.js"

# ---------- Etapa 2: aplicación ----------
FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libicu-dev unzip mariadb-client \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip intl bcmath opcache \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-cotizador.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/cotizador-entrypoint
RUN chmod +x /usr/local/bin/cotizador-entrypoint

WORKDIR /var/www/app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . .
COPY --from=assets /build/public/assets/ ./public/assets/
RUN composer dump-autoload --no-dev --optimize \
 && chown -R www-data:www-data storage

VOLUME ["/var/www/app/storage"]
EXPOSE 80
ENTRYPOINT ["cotizador-entrypoint"]
CMD ["apache2-foreground"]
