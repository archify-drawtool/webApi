# Stage 1: Builder – install Composer dependencies
FROM composer:2 AS builder

RUN apk add --no-cache libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev

# Stage 2: Runtime – nginx + php-fpm
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx supervisor curl python3 python3-pip python3-numpy \
        libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql opcache gd \
    && pip3 install --no-cache-dir --break-system-packages "opencv-contrib-python-headless==4.10.*" \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=builder /app /var/www/html
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD curl -f http://localhost/up || exit 1

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
