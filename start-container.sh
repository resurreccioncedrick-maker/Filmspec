#!/bin/bash

set -e

if [ "$IS_LARAVEL" = "true" ]; then
  # Clear any config cache baked in at build time BEFORE migrating. Railpack's
  # own build step runs `php artisan config:cache` without access to the
  # runtime service variables (DB_HOST, DB_PASSWORD, etc.), so that cached
  # file always resolves back to the default (sqlite) connection. The
  # upstream start-container.sh runs migrate before optimize:clear, so it
  # always migrates against the stale cached config — clearing it first here
  # ensures migrate reads the real runtime environment instead.
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
