# INSA POS performance deploy — 2026-05-28

## Deploy

1. Merge this commit to `deploy/insa` and push (Forge Quick Deploy on insapos.diybizrewards.com).
2. Confirm deploy log includes `npm run build` and `INSA deploy complete`.
3. On each tablet WebView: **force-stop INSAPOS** → reopen (or reinstall APK **3.0.11**).

## What changed

- **Web cashier**: max 48 product tiles per view, category/search required for catalogs &gt;200 SKUs, 500ms scan debounce, product lookup Maps, built Tailwind CSS (no CDN JIT on load).
- **`public/js/sync-engine.js`**: 60s sync when pending, **120s** when idle (replaces production **15s** `setInterval`).
- **Android**: push batching (3/cycle), backoff after failures, slower sync badge updates.

## Verify production

```bash
curl -s https://insapos.diybizrewards.com/js/sync-engine.js | head -20
# Expect: SYNC_INTERVAL_IDLE_MS = 120000 (not SYNC_INTERVAL_MS = 15000)
```

## User actions

- Cashier page: hard refresh once after deploy (or clear site data for insapos.diybizrewards.com).
- Install APK **v3.0.11** (versionCode 34) on tablets.
