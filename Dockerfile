# Single-container image for the assessment: PHP 8.3 CLI + SQLite, served by PHP's built-in server.
FROM php:8.3-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install dependencies first so this layer is cached between code changes.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p /data storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data /data storage bootstrap/cache \
    && chmod +x docker/entrypoint.sh

USER www-data

ENV DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    LOG_CHANNEL=stderr

EXPOSE 8000
VOLUME ["/data"]

ENTRYPOINT ["docker/entrypoint.sh"]
# Built-in server directly (not `artisan serve`, which drops most env vars such as DB_DATABASE).
CMD ["sh", "-c", "cd public && exec php -S 0.0.0.0:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"]
