#!/usr/bin/env bash
# ChamberQ production deploy — run on the server after .env is final.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "==> Maintenance mode"
php artisan down --retry=60 --refresh=15 || true

echo "==> Dependencies"
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

echo "==> Database"
php artisan migrate --force
php artisan catalogues:load

echo "==> Laravel caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Production gate"
php artisan app:production-check --strict

echo "==> Live"
php artisan up

echo "Deploy finished. Ensure cron runs: * * * * * php artisan schedule:run"
echo "Copy weekly backups from storage/app/backup-temp/ to durable off-server storage."
