#!/bin/bash
# Do NOT use set -e — we want Apache to start even if migrations fail

echo "==> Starting DinoRace..."

# ── 1. Minimal env setup ──────────────────────────────────────────
# For SQLite: ensure the file exists
DB_CONN="${DB_CONNECTION:-sqlite}"
if [ "$DB_CONN" = "sqlite" ]; then
    export DB_DATABASE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    if [ ! -f "$DB_DATABASE" ]; then
        echo "==> Creating SQLite database at $DB_DATABASE..."
        touch "$DB_DATABASE"
        chown www-data:www-data "$DB_DATABASE"
    fi
fi

# Generate APP_KEY only if completely missing
if [ -z "$APP_KEY" ]; then
    echo "==> Generating APP_KEY..."
    php artisan key:generate --force 2>&1 || echo "WARNING: key:generate failed"
fi

# ── 2. Only wait for external DB if NOT using SQLite ──────────────
if [ "$DB_CONN" != "sqlite" ] && [ -n "$DB_HOST" ]; then
    echo "==> Waiting for $DB_CONN database at $DB_HOST..."
    MAX_RETRIES=15
    COUNT=0
    until php artisan db:show 2>/dev/null | grep -q "mysql\|pgsql" || [ $COUNT -ge $MAX_RETRIES ]; do
        echo "    Database not ready, retrying in 2s... ($COUNT/$MAX_RETRIES)"
        sleep 2
        COUNT=$((COUNT+1))
    done
    if [ $COUNT -ge $MAX_RETRIES ]; then
        echo "WARNING: Database never became ready, proceeding anyway..."
    else
        echo "==> Database is ready!"
    fi
fi

# ── 3. Run migrations + seed (non-fatal) ─────────────────────────
echo "==> Running migrations..."
php artisan migrate --force --no-interaction 2>&1 || echo "WARNING: Migrations failed, continuing..."

echo "==> Seeding database..."
php artisan db:seed --force --no-interaction 2>&1 || echo "WARNING: db:seed failed, continuing..."

# ── 4. Clear caches (don't cache config — let env vars resolve at runtime) ─
echo "==> Clearing caches..."
php artisan config:clear  2>&1 || true
php artisan route:clear   2>&1 || true
php artisan view:cache    2>&1 || echo "WARNING: view:cache failed"

# ── 5. Start Apache ──────────────────────────────────────────────
echo "==> Starting Apache on port 8080..."
exec apache2-foreground
