# Laravel Forge deployment (INSA POS)

Production sites use **parted deploy branches** — see [DEPLOYMENT_SEPARATION.md](./DEPLOYMENT_SEPARATION.md).

| Site | Branch | Script |
|------|--------|--------|
| insapos.diybizrewards.com | `deploy/insa` | `scripts/forge-deploy-insa.sh` |
| insa-pos-rewards (Forge site) | `deploy/insa` | `scripts/forge-deploy-insa.sh` |
| epayplus.diybizrewards.com | `deploy/epayplus` | `scripts/forge-deploy-epayplus.sh` |

The repo root `deploy.sh` is a legacy generic template. New Forge sites should use the product scripts above.

Forge injects `FORGE_SITE_PATH`, `FORGE_SITE_BRANCH`, and `FORGE_COMPOSER` when the script runs on the server.

## Required: APP_PRODUCT in Forge Environment

Each Forge site must declare which product it runs. Without this, `scripts/forge-deploy-insa.sh` aborts with:

```text
ERROR: APP_PRODUCT must be 'insa' on this site (found: '<unset>')
```

**One-time setup for INSA sites** (insapos, insa-pos-rewards, etc.):

1. Forge → **Server** → **Site** → **Environment**
2. Add or confirm this line in the site's `.env`:

   ```env
   APP_PRODUCT=insa
   ```

3. Save. Redeploy.

For ePay Plus sites, use `APP_PRODUCT=epayplus` instead (see [DEPLOYMENT_PRODUCTS.md](./DEPLOYMENT_PRODUCTS.md)).

Optional: under **Site → Deployment**, enable **Make .env variables available to deployment script** so `APP_PRODUCT` is also visible as a shell variable during deploy.

The INSA deploy script can auto-write `APP_PRODUCT=insa` on first deploy when the Forge site path looks like an INSA host (`insapos`, `insa-pos-rewards`, etc.) and the value is missing or `auto`. Setting it explicitly in Forge Environment is still recommended so config is correct before the deploy hook runs.

## SSH access

Connect using one of:

- **Forge dashboard:** Server → your server → **SSH** (opens a browser session as the `forge` user).
- **Local terminal:** Use the SSH key registered on the server (e.g. `worker@forge.laravel.com` public key in Forge). Example:

  ```bash
  ssh forge@YOUR_SERVER_IP
  ```

Do not commit or share private keys. Only the public key belongs in Forge.

## Forge deploy script

In **Forge → Site → Deployment**:

1. Set **Deploy branch** to `deploy/insa` (not `main`). Enable **Quick Deploy** on that branch if desired.
2. Paste the contents of `scripts/forge-deploy-insa.sh` as the deploy script (not the root `deploy.sh`).

Forge sets `FORGE_SITE_PATH` automatically; the script falls back to `/home/forge/insapos.diybizrewards.com` if needed.

## Manual commands (if deploy fails)

SSH to the server, then:

```bash
cd $FORGE_SITE_PATH   # or cd /home/forge/insapos.diybizrewards.com
grep APP_PRODUCT .env  # must show APP_PRODUCT=insa
git pull origin deploy/insa
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
# php artisan queue:restart   # if you use queues
```

## Scheduler (required)

The `inventory:scan-expiry` command depends on Laravel's scheduler. On Forge, ensure the site cron runs every minute:

```bash
* * * * * cd /home/forge/insapos.diybizrewards.com && php artisan schedule:run >> /dev/null 2>&1
```

Forge usually adds this when you enable **Scheduler** on the site. Verify under **Site → Scheduler** or the server's cron entries.
