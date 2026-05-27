#!/usr/bin/env bash
# Paste this ENTIRE file into Forge → Site → Deployment → Deploy Script.
# Self-contained: sets APP_PRODUCT before git pull (dashboard script runs on old repo tree).
# Branch: deploy/insa | Site: insa-pos-rewards-tasxesjq.on-forge.com and similar INSA hosts.

export APP_PRODUCT=insa

set -euo pipefail

cd "${FORGE_SITE_PATH:-/home/forge/insa-pos-rewards-tasxesjq.on-forge.com}"

if [ ! -f .env ]; then
  echo "ERROR: .env not found in $(pwd)"
  exit 1
fi

if grep -qE '^APP_PRODUCT=' .env; then
  sed -i.bak 's/^APP_PRODUCT=.*/APP_PRODUCT=insa/' .env
  rm -f .env.bak
else
  printf '\nAPP_PRODUCT=insa\n' >> .env
fi

git pull origin "${FORGE_SITE_BRANCH:-deploy/insa}"

if grep -r '<<<<<<<' routes/ 2>/dev/null; then
  echo "ERROR: Git merge conflict markers found in routes/ — fix before deploying"
  exit 1
fi

${FORGE_COMPOSER:-composer} install --no-dev --no-interaction --prefer-dist --optimize-autoloader

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

if php artisan list --raw 2>/dev/null | grep -q '^queue:restart'; then
  php artisan queue:restart
fi

echo "INSA deploy complete ($(git rev-parse --short HEAD))"
