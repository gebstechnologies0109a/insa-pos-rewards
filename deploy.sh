#!/bin/bash
# Legacy generic template. Prefer scripts/forge-deploy-insa.sh or scripts/forge-deploy-epayplus.sh.
# INSA Forge sites require APP_PRODUCT=insa in Site → Environment (see docs/FORGE_DEPLOY.md).
cd $FORGE_SITE_PATH || cd /home/forge/insapos.diybizrewards.com

git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# optional: php artisan queue:restart if queues used

echo "Deploy complete"
