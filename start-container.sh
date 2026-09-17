#!/bin/bash

set -e

if [ "$IS_LARAVEL" = "true" ]; then
  # FrankenPHP does not reliably expose the container's runtime environment
  # variables to PHP's env()/getenv() (a known FrankenPHP goroutine/env-array
  # inconsistency), so Laravel keeps resolving config defaults (e.g. sqlite)
  # even after clearing the config cache. Writing a real .env file from the
  # container's actual environment lets Laravel's normal dotenv loading pick
  # these values up reliably instead.
  cat > /app/.env <<EOF
APP_NAME="${APP_NAME}"
APP_ENV=${APP_ENV}
APP_KEY=${APP_KEY}
APP_DEBUG=${APP_DEBUG}
APP_URL=${APP_URL}
DB_CONNECTION=${DB_CONNECTION}
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}
SESSION_DRIVER=${SESSION_DRIVER}
SESSION_TABLE=${SESSION_TABLE}
CACHE_STORE=${CACHE_STORE}
QUEUE_CONNECTION=${QUEUE_CONNECTION}
MAIL_MAILER=${MAIL_MAILER}
MAIL_HOST=${MAIL_HOST}
MAIL_PORT=${MAIL_PORT}
MAIL_USERNAME=${MAIL_USERNAME}
MAIL_PASSWORD="${MAIL_PASSWORD}"
MAIL_ENCRYPTION=${MAIL_ENCRYPTION}
MAIL_FROM_ADDRESS=${MAIL_FROM_ADDRESS}
MAIL_FROM_NAME="${MAIL_FROM_NAME}"
BREVO_API_KEY=${BREVO_API_KEY}
EOF

  php artisan config:clear

  if [ "$RAILPACK_SKIP_MIGRATIONS" != "true" ]; then
    echo "Running migrations and seeding database ..."
    # Railway briefly runs more than one instance of this container during a
    # restart/deploy, so two processes can race to create the same table.
    # Don't let a losing race (table already exists) crash the whole
    # container — the winning instance already finished the schema.
    php artisan migrate --force || echo "migrate exited non-zero (possibly a concurrent-deploy race); continuing boot"
  fi

  php artisan storage:link
  php artisan optimize:clear
  php artisan optimize

  echo "Starting Laravel server ..."
fi

# Start the FrankenPHP server
docker-php-entrypoint --config /Caddyfile --adapter caddyfile 2>&1
