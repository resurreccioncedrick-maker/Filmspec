#!/bin/bash

set -e

if [ "$IS_LARAVEL" = "true" ]; then
  # TEMPORARY DIAGNOSTIC — remove once we've confirmed where DB_CONNECTION etc.
  # actually live. Prints variable NAMES only (never values) so no secrets
  # leak into the log, just answers "is this variable visible to bash here at all".
  echo "--- ENV DIAGNOSTIC: variable names visible to this script ---"
  env | cut -d= -f1 | sort
  echo "--- DB_CONNECTION is set: $([ -n "${DB_CONNECTION+x}" ] && echo yes || echo no), non-empty: $([ -n "$DB_CONNECTION" ] && echo yes || echo no) ---"
  echo "--- END ENV DIAGNOSTIC ---"

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
EOF

  php artisan config:clear

  if [ "$RAILPACK_SKIP_MIGRATIONS" != "true" ]; then
    echo "Running migrations and seeding database ..."
    php artisan migrate --force
  fi

  php artisan storage:link
  php artisan optimize:clear
  php artisan optimize

  echo "Starting Laravel server ..."
fi

# Start the FrankenPHP server
docker-php-entrypoint --config /Caddyfile --adapter caddyfile 2>&1
