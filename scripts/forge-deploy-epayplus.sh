#!/usr/bin/env bash
# Laravel Forge deploy hook — ePay Plus (epayplus.diybizrewards.com)
# Forge sets FORGE_SITE_PATH, FORGE_SITE_BRANCH, FORGE_COMPOSER.
# Point the site at branch deploy/epayplus and call this script from Deployment.

set -euo pipefail

cd "${FORGE_SITE_PATH:-/home/forge/epayplus.diybizrewards.com}"

if [ ! -f .env ]; then
  echo "ERROR: .env not found in $(pwd)"
  exit 1
fi

PRODUCT="$(grep -E '^APP_PRODUCT=' .env | tail -1 | cut -d= -f2- | tr -d "'\" \r" || true)"
if [ "${PRODUCT}" != "epayplus" ]; then
  echo "ERROR: APP_PRODUCT must be 'epayplus' on this site (found: '${PRODUCT:-<unset>}')"
  exit 1
fi

git pull origin "${FORGE_SITE_BRANCH:-deploy/epayplus}"

if grep -r '<<<<<<<' routes/ 2>/dev/null; then
  echo "ERROR: Git merge conflict markers found in routes/ — fix before deploying"
  exit 1
fi

${FORGE_COMPOSER:-composer} install --no-dev --no-interaction --prefer-dist --optimize-autoloader

php artisan migrate --force

php artisan epay:sync-dual-wallets

# Optional catalog refresh (manual; see docs/EPAYPLUS_SEEDING.md):
# php artisan db:seed --class=EPayPlusSeeder --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

if php artisan list --raw 2>/dev/null | grep -q '^queue:restart'; then
  php artisan queue:restart
fi

echo "ePay Plus deploy complete ($(git rev-parse --short HEAD))"
