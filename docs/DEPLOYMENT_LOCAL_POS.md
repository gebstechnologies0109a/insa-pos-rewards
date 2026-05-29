# INSA POS v3 — Local Engine Deployment Checklist

For **Ronaldo** / Forge deploy on `deploy/insa` branch.

## Pre-deploy (local)

```bash
cd C:/laragon/www/INSA_POS
php artisan test --filter=Sync
php artisan test --filter=Company
php artisan test --filter=Device
php artisan test --filter=Session
php artisan test --filter=StockIn
cd INSAPOSv2
./gradlew assembleDebug
```

## Forge / production

1. **Pull branch** `deploy/insa` on server (`insapos.diybizrewards.com`).
2. **Migrations** (idempotent):
   ```bash
   php artisan migrate --force
   ```
3. **Seed hierarchy** (manual — not in auto-deploy):
   ```bash
   php artisan db:seed --class=CompanyBranchDeviceSeeder
   ```
   Creates **GEBS** → **INSAPOS** branch, devices from `pos_terminal_sessions` fingerprints.
4. **Cache** (if used):
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
5. **APK**: install `INSAPOSv2/app/build/outputs/apk/debug/app-debug.apk` (v3.0.12+) on terminals.

## Smoke test

- [ ] Super Admin → Companies → GEBS → Branches → INSAPOS → Devices listed
- [ ] `POST /api/pos/license/validate` with device fingerprint
- [ ] `POST /api/pos/terminal/register` (or `/api/pos/session/start`) — seat within `pos_slots`
- [ ] Cashier loads products via `INSAPOS.getLocalProducts` when Android bridge active
- [ ] Offline sale → `createLocalSale` → `triggerLocalSync` → server `sync/push` idempotent
- [ ] `GET /api/pos/sync/pull?branch_id=` returns products, categories, customers, batches, alerts, settings

## Hierarchy

`COMPANY (GEBS) → BRANCH (INSAPOS) → DEVICE (fingerprint) → POS TERMINAL SESSION`
