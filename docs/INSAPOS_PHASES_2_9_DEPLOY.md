# INSA POS Full System Upgrade — Phases 2–9 Deploy

**Date:** 2026-05-28  
**Branch:** `deploy/insa`  
**APK:** INSAPOS v3.0.12 (versionCode 35)

## What shipped

| Phase | Status |
|-------|--------|
| 2 Super Admin (Company/Branch/Device) | Already in repo — verified |
| 3 CompanyBranchDeviceSeeder | Already in repo — verified |
| 4 Android `posengine/` + `/local/*` APIs | **New** |
| 5 Laravel sync + `/api/pos/license/validate` | **Enhanced** |
| 6 SyncEngine v3 (12–15s loop, backoff, conflicts) | **New** |
| 7 Local-first sale path on Android WebView | **New** |
| 8 PHPUnit tests (hierarchy, license, sync, seeder) | **New** |
| 9 APK build + deploy docs | This document |

## Forge deploy (insapos.diybizrewards.com)

```bash
cd /home/forge/insapos.diybizrewards.com
git pull origin deploy/insa
export APP_PRODUCT=insa
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
```

## Post-deploy verification

```bash
php artisan test --filter="CompanyHierarchy|PosSyncIdempotency|PosLicenseValidate|CompanyBranchDeviceSeeder|PosOfflinePrefetch"
```

## Tablet rollout

1. Install APK: `INSAPOSv2/app/build/outputs/apk/debug/app-debug.apk`
2. Force-stop INSAPOS → reopen
3. Login → catalog pulls via native SyncEngine; checkout uses `createLocalSale`

## Seeder (production)

```bash
php artisan db:seed --class=CompanyBranchDeviceSeeder
```

### Manual device fingerprints (if seeder finds 0)

1. Super Admin → Devices → add device with fingerprint from `INSAPOS.getDeviceFingerprint()` on each tablet
2. Re-run seeder

## APK build

```bash
cd INSAPOSv2
./gradlew assembleDebug
```

Output: `INSAPOSv2/app/build/outputs/apk/debug/app-debug.apk`
