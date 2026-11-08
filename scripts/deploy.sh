#!/usr/bin/env bash
# Production deploy: migrations + users/settings/authorities seed (no fine data).
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> Composer install"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

echo "==> Migrate + bootstrap (users, settings, authorities)"
php artisan vio:bootstrap-production --migrate --optimize-clear

echo "==> Deploy finished"
