#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Allow overriding the run mode via env (preferred when CMD args are awkward to set)
MODE="${SERVICE_MODE:-${1:-web}}"

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
# Uses a DB flag so it survives container restarts without a persistent volume.
if [ "$MODE" = "web" ] && [ "${RUN_SEED:-true}" = "true" ]; then
    SEEDED=$(php artisan tinker --no-interaction --execute="echo \DB::table('tenants')->exists() ? '1' : '0';" 2>/dev/null | tail -1 || echo "0")
    if [ "$SEEDED" != "1" ]; then
        php artisan db:seed --force --class=DatabaseSeeder || true
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
