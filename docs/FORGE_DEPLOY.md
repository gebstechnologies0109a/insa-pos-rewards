# Laravel Forge deployment (INSA POS)

Production sites use **parted deploy branches** — see [DEPLOYMENT_SEPARATION.md](./DEPLOYMENT_SEPARATION.md).

| Site | Branch | Script |
|------|--------|--------|
| insapos.diybizrewards.com | `deploy/insa` | `scripts/forge-deploy-insa.sh` |
| insa-pos-rewards (Forge site, e.g. `insa-pos-rewards-tasxesjq.on-forge.com`) | `deploy/insa` | `scripts/forge-deploy-insa.sh` |
| epayplus.diybizrewards.com | `deploy/epayplus` | `scripts/forge-deploy-epayplus.sh` |

The repo root `deploy.sh` is a legacy entry point that **delegates** to the product scripts when the Forge site path/name looks like INSA or ePay Plus. New Forge sites should still paste `scripts/forge-deploy-insa.sh` directly in the dashboard.

Forge injects `FORGE_SITE_PATH`, `FORGE_SITE_BRANCH`, `FORGE_SITE_NAME`, and `FORGE_COMPOSER` when the script runs on the server.

## Critical: deploy script runs before `git pull`

Forge runs whatever is pasted under **Site → Deployment → Deploy Script** on **every** deploy. That script executes **before** `git pull` updates the repo on disk.

If the dashboard still contains an old copy of `forge-deploy-insa.sh` (for example from commit `be335e4`), deploy will fail on `APP_PRODUCT` even when branch `deploy/insa` already has the fix. You must **update the deploy script in Forge once** (paste the latest `scripts/forge-deploy-insa.sh` from this repo) **or** set `APP_PRODUCT=insa` in Forge Environment so the guard passes on the first line.

After the dashboard script is updated, future deploys pick up repo changes via `git pull` inside the script.

## APP_PRODUCT (INSA sites)

Recommended one-time setup:

1. Forge → **Server** → **Site** → **Environment**
2. Add or confirm:

   ```env
   APP_PRODUCT=insa
   ```

3. Under **Site → Deployment**, enable **Make .env variables available to deployment script**.
4. Save. Redeploy.

`scripts/forge-deploy-insa.sh` also auto-sets `APP_PRODUCT=insa` when the value is missing or `auto` and the Forge site path/name looks like an INSA host (`insapos`, `insa-pos-rewards`, `insa-pos`, etc.). It **only fails** when `APP_PRODUCT` is set to a **wrong** product (e.g. `epayplus` on an INSA site).

For ePay Plus sites, use `APP_PRODUCT=epayplus` instead (see [DEPLOYMENT_PRODUCTS.md](./DEPLOYMENT_PRODUCTS.md)).

## Forge dashboard checklist (INSA POS rewards site)

Use this for `insa-pos-rewards-tasxesjq.on-forge.com` and similar INSA Forge sites:

| Setting | Value |
|---------|--------|
| **Deploy branch** | `deploy/insa` (not `main`) |
| **Deploy script** | Full contents of `scripts/forge-deploy-insa.sh` from latest `deploy/insa` (not root `deploy.sh` unless you accept delegation) |
| **Environment** | `APP_PRODUCT=insa` |
| **Deployment option** | Enable **Make .env variables available to deployment script** |
| **Server** → Forge worker key | Line with `worker@forge.laravel.com` in `/home/forge/.ssh/authorized_keys` (see below) |

Then click **Deploy Now** (or push to `deploy/insa` if Quick Deploy is on).

Verify deploy log shows a recent commit (after `48e75df` / auto-`APP_PRODUCT` fix), not an old SHA like `be335e4` unless that is intentionally deployed.

## SSH access

Connect using one of:

- **Forge dashboard:** Server → your server → **SSH** (opens a browser session as the `forge` user).
- **Local terminal:** Use the SSH key registered on the server (e.g. `worker@forge.laravel.com` public key in Forge). Example:

  ```bash
  ssh forge@YOUR_SERVER_IP
  ```

Do not commit or share private keys. Only the public key belongs in Forge.

## Manual commands (if deploy fails)

SSH to the server, then:

```bash
cd $FORGE_SITE_PATH   # e.g. /home/forge/insa-pos-rewards-tasxesjq.on-forge.com
grep APP_PRODUCT .env  # should show APP_PRODUCT=insa (script may add it)
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
