#!/bin/sh
set -eu

if [ -z "${APP_KEY:-}" ] || [ -z "${JWT_SECRET:-}" ] || [ -z "${DB_PASSWORD:-}" ]; then
    echo "APP_KEY, JWT_SECRET, and DB_PASSWORD must be set." >&2
    exit 1
fi

if [ "${1:-}" = "apache2-foreground" ]; then
    su -s /bin/sh www-data -c '
        php artisan config:clear --no-ansi &&
        php artisan route:clear --no-ansi &&
        php artisan view:clear --no-ansi &&
        php artisan event:clear --no-ansi &&
        php artisan package:discover --ansi
    '
fi

exec "$@"
