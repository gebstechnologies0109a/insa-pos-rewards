# Laravel Forge deployment (INSA POS)

This repository includes a standard Forge deploy script at the repo root: `deploy.sh`. Forge injects `FORGE_SITE_PATH`, `FORGE_SITE_BRANCH`, and `FORGE_COMPOSER` when the script runs on the server.

## SSH access

Connect using one of:

- **Forge dashboard:** Server → your server → **SSH** (opens a browser session as the `forge` user).
- **Local terminal:** Use the SSH key registered on the server (e.g. `worker@forge.laravel.com` public key in Forge). Example:

  ```bash
  ssh forge@YOUR_SERVER_IP
  ```

Do not commit or share private keys. Only the public key belongs in Forge.

## Forge deploy script

In **Forge → Site → insapos (insapos.diybizrewards.com) → Deployment**:

1. Paste the contents of `deploy.sh`, **or**
2. Enable **Quick Deploy** / deploy on push to `main`, then use **Deploy Now** after pushes.

Forge sets `FORGE_SITE_PATH` automatically; the script falls back to `/home/forge/insapos.diybizrewards.com` if needed.

## Manual commands (if deploy fails)

SSH to the server, then:

```bash
cd $FORGE_SITE_PATH   # or cd /home/forge/insapos.diybizrewards.com
git pull origin main
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
