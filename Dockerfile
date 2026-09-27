FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

FROM php:8.3-cli-alpine
RUN docker-php-ext-install pcntl
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY *.php animation.json ./
USER nobody
CMD ["php", "main.php"]
