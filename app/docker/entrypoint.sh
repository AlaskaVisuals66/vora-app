#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Allow overriding the run mode via env (preferred when CMD args are awkward to set)
MODE="${SERVICE_MODE:-${1:-web}}"

# Ensure SQLite file exists when DB_CONNECTION=sqlite (default for this app)
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_PATH="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    mkdir -p "$(dirname "$DB_PATH")"
    if [ ! -f "$DB_PATH" ]; then
        touch "$DB_PATH"
    fi
    chown www-data:www-data "$DB_PATH"
fi

# Make sure writable directories belong to www-data (covers volume-mounted dirs)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database 2>/dev/null || true

# Cache config / routes / views (skip if APP_KEY missing — composer build leaves them stale)
if [ -n "${APP_KEY:-}" ]; then
    php artisan config:cache  || true
    php artisan route:cache   || true
    php artisan view:cache    || true
fi

# Storage symlink for public uploads
php artisan storage:link --force >/dev/null 2>&1 || true

# Run migrations only on the web container (not on horizon worker, avoids race)
if [ "$MODE" = "web" ] && [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force || true
fi

# One-shot seed on first boot (creates default tenant, roles, users)
SEED_LOCK="/var/www/html/database/.seeded"
if [ "$MODE" = "web" ] && [ "${RUN_SEED:-true}" = "true" ] && [ ! -f "$SEED_LOCK" ]; then
    if php artisan db:seed --force --class=DatabaseSeeder; then
        touch "$SEED_LOCK"
        chown www-data:www-data "$SEED_LOCK" 2>/dev/null || true
    fi
fi

# Re-chown after artisan commands wrote logs/cache as root
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database 2>/dev/null || true

case "$MODE" in
    web)
        # Supervisord now runs nginx + php-fpm + horizon together (single-container setup)
        exec /usr/bin/supervisord -c /etc/supervisord.conf
        ;;
    horizon)
        # Standalone horizon mode (kept for backward compat / scaling out, not used in the
        # default Easy Panel deploy where web already runs horizon via supervisord).
        exec su -s /bin/sh -c 'php artisan horizon' www-data
        ;;
    schedule)
        # Minimal cron loop: runs scheduler every 60s in foreground
        while true; do
            su -s /bin/sh -c 'php artisan schedule:run --no-interaction' www-data || true
            sleep 60
        done
        ;;
    artisan)
        shift
        exec su -s /bin/sh -c "php artisan $*" www-data
        ;;
    *)
        exec "$@"
        ;;
esac
