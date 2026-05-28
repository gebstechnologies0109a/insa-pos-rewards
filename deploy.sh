#!/usr/bin/env bash
# Legacy Forge entry point — delegates to product deploy scripts when possible.
# Prefer pasting scripts/forge-deploy-insa.sh or scripts/forge-deploy-epayplus.sh in Forge → Deployment.

_ctx="$(printf '%s' "${FORGE_SITE_PATH:-}${FORGE_SITE_ROOT:-}${FORGE_SITE_NAME:-}${FORGE_SITE_DIRECTORY:-}" | tr '[:upper:]' '[:lower:]')"

case "${_ctx}" in
  *insapos*|*insa-pos-rewards*|*insa_pos_rewards*|*insa-pos*|*insa*)
    export APP_PRODUCT=insa
    ;;
  *epayplus*)
    export APP_PRODUCT=epayplus
    ;;
esac

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

case "${_ctx}" in
  *insapos*|*insa-pos-rewards*|*insa_pos_rewards*|*insa-pos*|*insa*)
    exec "${REPO_ROOT}/scripts/forge-deploy-insa.sh"
    ;;
  *epayplus*)
    exec "${REPO_ROOT}/scripts/forge-deploy-epayplus.sh"
    ;;
esac

cd "${FORGE_SITE_PATH:-/home/forge/insapos.diybizrewards.com}"

git pull origin "${FORGE_SITE_BRANCH:-main}"

${FORGE_COMPOSER:-composer} install --no-dev --no-interaction --prefer-dist --optimize-autoloader

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# optional: php artisan queue:restart if queues used

echo "Deploy complete"
