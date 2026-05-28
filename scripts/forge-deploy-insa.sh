#!/usr/bin/env bash
# Laravel Forge deploy hook — INSA POS (insapos.diybizrewards.com, insa-pos-rewards, etc.)
# Forge sets FORGE_SITE_PATH, FORGE_SITE_BRANCH, FORGE_COMPOSER, FORGE_SITE_NAME.
# Point the site at branch deploy/insa and paste this script (or forge-deploy-insa-standalone.sh) in Site → Deployment.
#
# Recommended in Forge → Site → Environment: APP_PRODUCT=insa
# Enable "Make .env variables available to deployment script" so APP_PRODUCT is visible here.

# MUST be first executable logic — Forge runs the dashboard script before git pull.
_insactx="$(printf '%s' "${FORGE_SITE_PATH:-}${FORGE_SITE_ROOT:-}${FORGE_SITE_NAME:-}${FORGE_SITE_DIRECTORY:-}" | tr '[:upper:]' '[:lower:]')"
case "${_insactx}" in
  *insapos*|*insa-pos-rewards*|*insa_pos_rewards*|*insa-pos*|*insa*)
    export APP_PRODUCT=insa
    ;;
esac
if [ -z "${APP_PRODUCT:-}" ] || [ "${APP_PRODUCT}" = "auto" ]; then
  case "${_insactx}" in
    *insapos*|*insa-pos-rewards*|*insa_pos_rewards*|*insa-pos*|*insa*)
      export APP_PRODUCT=insa
      ;;
  esac
fi

set -euo pipefail

is_insa_forge_site() {
  case "${_insactx}" in
    *insapos*|*insa-pos-rewards*|*insa_pos_rewards*|*insa-pos*|*insa*)
      return 0
      ;;
  esac
  return 1
}

sync_insa_app_product_to_env() {
  if ! is_insa_forge_site; then
    return 0
  fi
  export APP_PRODUCT=insa
  if [ -f .env ]; then
    if grep -qE '^APP_PRODUCT=' .env; then
      sed -i.bak 's/^APP_PRODUCT=.*/APP_PRODUCT=insa/' .env
      rm -f .env.bak
    else
      printf '\n# Set by forge-deploy-insa.sh — prefer Forge → Site → Environment\nAPP_PRODUCT=insa\n' >> .env
    fi
  fi
}

read_app_product() {
  local from_env="${APP_PRODUCT:-}"
  if [ -n "${from_env}" ] && [ "${from_env}" != "auto" ]; then
    echo "${from_env}"
    return
  fi
  if [ -f .env ]; then
    grep -E '^APP_PRODUCT=' .env | tail -1 | cut -d= -f2- | tr -d "'\" \r" || true
    return
  fi
  echo ""
}

cd "${FORGE_SITE_PATH:-/home/forge/insapos.diybizrewards.com}"

if [ ! -f .env ]; then
  echo "ERROR: .env not found in $(pwd)"
  exit 1
fi

# INSA Forge sites: default to insa; only abort when explicitly misconfigured.
if is_insa_forge_site; then
  export APP_PRODUCT=insa
fi

PRODUCT="$(read_app_product)"
if [ -z "${PRODUCT}" ] || [ "${PRODUCT}" = "auto" ]; then
  if is_insa_forge_site; then
    PRODUCT=insa
    export APP_PRODUCT=insa
    sync_insa_app_product_to_env
  fi
fi

if [ "${PRODUCT}" = "epayplus" ]; then
  echo "ERROR: APP_PRODUCT=epayplus on an INSA Forge site — set APP_PRODUCT=insa in Forge → Environment"
  exit 1
fi

if [ -n "${PRODUCT}" ] && [ "${PRODUCT}" != "insa" ]; then
  echo "ERROR: APP_PRODUCT must be 'insa' on this site (found: '${PRODUCT}')"
  echo "Fix: Forge → Site → Environment → add APP_PRODUCT=insa (then redeploy)"
  exit 1
fi

if [ "${PRODUCT}" != "insa" ]; then
  if is_insa_forge_site; then
    PRODUCT=insa
    export APP_PRODUCT=insa
    sync_insa_app_product_to_env
  else
    echo "WARN: APP_PRODUCT unset on non-INSA-named site — continuing with APP_PRODUCT=insa for this script"
    export APP_PRODUCT=insa
    PRODUCT=insa
  fi
fi

git pull origin "${FORGE_SITE_BRANCH:-deploy/insa}"

if grep -r '<<<<<<<' routes/ 2>/dev/null; then
  echo "ERROR: Git merge conflict markers found in routes/ — fix before deploying"
  exit 1
fi

${FORGE_COMPOSER:-composer} install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if [ -f package.json ] && command -v npm >/dev/null 2>&1; then
  npm ci --no-audit --no-fund 2>/dev/null || npm install --no-audit --no-fund
  npm run build
fi

php artisan migrate --force

# Optional seeders (run manually when needed; do not enable on every deploy):
# php artisan db:seed --class=InitialSetupSeeder --force
# php artisan db:seed --class=SuperAdminSeeder --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

if php artisan list --raw 2>/dev/null | grep -q '^queue:restart'; then
  php artisan queue:restart
fi

echo "INSA deploy complete ($(git rev-parse --short HEAD))"
