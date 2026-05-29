# Changelog

All notable INSAPOS / INSA POS releases are documented here.

## [3.0.47] — 2026-05-29

**Release by Ronaldo** — disk-backed catalog, delta sync, storage print queue, gear/CD settings, light customer-display theme, cafe category reload fix.

### Android (APK `com.insapos.v2` / debug `com.insapos.v2.debug`)

- **Disk-backed catalog** — `CatalogDownloadManager` streams catalog to disk; `CatalogStreamImporter` loads SQLite without full JS hydration.
- **Background delta sync** — catalog pull suppressed during stats reads; import status exposed to WebView.
- **Storage-first print spool** — `PrintSpooler` queues receipts on device storage before printer I/O.
- **Gear / settings** — full POS settings modal from native toolbar; customer display toggles and media sync.
- **Customer display** — light theme refresh; cart mirror and settings API hooks.
- **Version** — `3.0.47` (versionCode 69).

### Laravel / Blade (requires Forge deploy to `deploy/insa`)

- Cashier gear modal: customer display photo/video upload, orientation, rotation, show-cart.
- Product lookup ETag support; customer display settings API (`/settings/customer-display/*`).
- **Cafe category dropdown fix** — `loadNativeCategories()` on catalog import complete, store download finish, and `refreshProductsFromDB()` when using storage catalog; retry after local-service ping; warning toast when SQLite has categories but the dropdown is empty.

### Deploy notes

- Push/merge to **`deploy/insa`**; Forge runs `scripts/forge-deploy-insa.sh` (`php artisan view:cache` included).
- APK alone is not enough for cafe dropdown — **blade v3.0.47+ must be live** on `insapos.diybizrewards.com`.
- GitHub tag: **`insapos-v3.0.47`**; unsigned APK on release assets (sign before fleet rollout).
