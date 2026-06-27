# Dev/demo image for an EventHub Laravel service. Serves via artisan on $APP_PORT.
# PHP 8.4 matches the committed composer.lock (symfony 8.x requires php >=8.4.1); composer.json allows ^8.2.
FROM php:8.4-cli

RUN apt-get update && apt-get install -y git unzip libzip-dev \
    && docker-php-ext-install pdo_mysql bcmath zip \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app
RUN composer install --no-interaction --prefer-dist --no-progress

# APP_PORT is provided by docker-compose (8000 core-api / 8001 payment-service).
CMD php artisan serve --host=0.0.0.0 --port=${APP_PORT:-8000}
