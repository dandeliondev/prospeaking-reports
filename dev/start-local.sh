#!/usr/bin/env bash
# Run without Docker: symlinks /srv/www paths, seeds MySQL if available, starts PHP server.
set -euo pipefail

REPO="$(cd "$(dirname "$0")/.." && pwd)"
export DB_HOST="${DB_HOST:-127.0.0.1}"
export DB_USER="${DB_USER:-root}"
export DB_PASS="${DB_PASS:-prospeaking}"
export DB_PORT="${DB_PORT:-3306}"

if ! command -v php >/dev/null 2>&1; then
    echo "PHP is required. Install php-cli and php-mysqli."
    exit 1
fi

if ! php -m 2>/dev/null | grep -q mysqli; then
    echo "PHP mysqli extension is required."
    exit 1
fi

seed_db() {
    if ! command -v mysql >/dev/null 2>&1; then
        echo "mysql client not found — import dev/seed.sql manually if the report errors."
        return 0
    fi
    echo "Seeding database (if not already done)..."
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" < "$REPO/dev/seed.sql" 2>/dev/null || {
        echo "Could not seed MySQL. Create DB user/password or run:"
        echo "  mysql -u root -p < dev/seed.sql"
    }
}

seed_db

echo ""
echo "Starting PHP on http://localhost:8080"
echo "Open http://localhost:8080/Reports/DPH/index.php and click Submit."
echo ""

cd "$REPO"
exec php -S localhost:8080
