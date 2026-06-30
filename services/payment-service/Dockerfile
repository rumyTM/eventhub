# Dev/demo image for an EventHub Laravel service. Serves via artisan on $APP_PORT.
# PHP 8.4 matches the committed composer.lock (symfony 8.x requires php >=8.4.1); composer.json allows ^8.2.
FROM php:8.4-cli

RUN apt-get update && apt-get install -y git unzip libzip-dev \
    && docker-php-ext-install pdo_mysql bcmath zip \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install dependencies FIRST, keyed only on the composer manifests, so this layer (the slow,
# network-dependent step) is cached and re-runs only when composer.json/lock change — not on every
# app-code edit. --no-scripts/--no-autoloader because artisan package:discover needs the full app.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader

# Now copy the app and finish the autoloader (this also runs post-autoload-dump → package:discover).
COPY . /app
RUN composer dump-autoload --optimize --no-interaction

# Entrypoint merges Docker runtime env vars into .env before PHP starts (see script for why).
RUN chmod +x /app/docker-entrypoint.sh
ENTRYPOINT ["/app/docker-entrypoint.sh"]

# APP_PORT is provided by docker-compose (8000 core-api / 8001 payment-service).
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8001"]
