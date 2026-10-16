#!/bin/sh
set -e

# .env is optional in the container: real values come from environment variables.
[ -f .env ] || cp .env.example .env

if [ -z "$APP_KEY" ] && ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

touch "$DB_DATABASE"
php artisan migrate --force --no-interaction

# Catalogue is idempotent; the demo backlog is loaded only on the first start of the volume.
php artisan db:seed --class=Database\\Seeders\\ProductCatalogSeeder --force --no-interaction
if [ "${SEED_DEMO_DATA:-true}" = "true" ] && [ ! -f /data/.demo-seeded ]; then
    php artisan db:seed --class=Database\\Seeders\\DemoOrderSeeder --force --no-interaction
    touch /data/.demo-seeded
fi

exec "$@"
