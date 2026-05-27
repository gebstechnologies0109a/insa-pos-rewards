# Deployment separation (INSA vs ePay Plus)

Both products live in one Git repository but deploy to **separate Forge sites** on **separate branches**. A push to `main` no longer automatically ships to both production hosts unless you merge into each deploy branch.

## Branch map

| Forge site | Hostname | Deploy branch | Deploy script |
|------------|----------|---------------|---------------|
| **insapos** | `insapos.diybizrewards.com` | `deploy/insa` | `scripts/forge-deploy-insa.sh` |
| **epayplus** | `epayplus.diybizrewards.com` | `deploy/epayplus` | `scripts/forge-deploy-epayplus.sh` |

| Branch | Role |
|--------|------|
| `main` | Integration — feature work merges here via PR |
| `deploy/insa` | Production line for INSA POS web + shared Laravel core |
| `deploy/epayplus` | Production line for ePay Plus web/API + shared Laravel core |

## Release workflow

1. Develop on feature branches; merge to `main` when ready.
2. When **INSA** is ready to release: merge `main` → `deploy/insa` (PR recommended), then deploy the insapos Forge site (or rely on Quick Deploy if enabled on that branch).
3. When **ePay Plus** is ready to release: merge `main` → `deploy/epayplus` separately — no need to wait for INSA or vice versa.
4. Shared migrations in `database/migrations/` apply on both sites when each deploy branch is released; test on staging or one product first if the migration is risky.

```bash
# Release INSA only
git checkout deploy/insa
git merge origin/main
git push origin deploy/insa

# Release ePay Plus only (independent)
git checkout deploy/epayplus
git merge origin/main
git push origin deploy/epayplus
```

## What this fixes

Previously both Forge sites tracked **`main`**. Any commit on `main` could trigger (or be pulled by) both sites, so a change aimed at one product could break the other before the second site was ready.

Now each site tracks its own deploy branch. They can point at **different commits** safely because:

- Each site has its own `.env` with `APP_PRODUCT=insa` or `APP_PRODUCT=epayplus` (required).
- Deploy scripts verify `APP_PRODUCT` and abort if misconfigured.
- Deploy scripts scan `routes/` for merge conflict markers before running Artisan.

**Do not** point both Forge sites at the same branch without product env separation — that was the old failure mode.

## Forge UI changes (required once)

For each site: **Forge → Server → Site → App → Deployment**

### insapos.diybizrewards.com

| Setting | Value |
|---------|--------|
| **Deploy branch** | `deploy/insa` |
| **Deploy script** | Contents of `scripts/forge-deploy-insa.sh` (or append its steps after `git pull`) |
| **Quick Deploy** | Optional — enable only if you want auto-deploy on push to `deploy/insa` |

Confirm `.env` contains `APP_PRODUCT=insa`.

### epayplus.diybizrewards.com

| Setting | Value |
|---------|--------|
| **Deploy branch** | `deploy/epayplus` |
| **Deploy script** | Contents of `scripts/forge-deploy-epayplus.sh` |
| **Quick Deploy** | Optional — enable only on `deploy/epayplus` |

Confirm `.env` contains `APP_PRODUCT=epayplus`.

The root `deploy.sh` remains a generic template; new sites should use the product-specific scripts above.

## Product-specific deploy steps

**INSA** (`forge-deploy-insa.sh`): migrations + cache rebuild. Seeders (`InitialSetupSeeder`, `SuperAdminSeeder`) are **commented out** — run manually when onboarding a new environment.

**ePay Plus** (`forge-deploy-epayplus.sh`): migrations, `php artisan epay:sync-dual-wallets`, cache rebuild. See [EPAYPLUS_DUAL_WALLET_DEPLOY.md](./EPAYPLUS_DUAL_WALLET_DEPLOY.md). `EPayPlusSeeder` stays manual.

## CI

- Android builds for INSA apps run on changes under `INSAPOS*`, `INSABuddy`, etc., or pushes to `deploy/insa`.
- ePay Plus APK builds run on changes under `ePayPlus/` or pushes to `deploy/epayplus`.
- Pushes to `main` do not auto-merge into deploy branches; see `.github/workflows/deploy-branches.yml` for sync reminders.

## Phase 2 (optional, not implemented)

If branch separation is not enough long term, split the monorepo into two GitHub repositories:

- `insa-pos` — INSA web, `INSAPOS*`, shared core extracted via `git subtree split`
- `epayplus` — ePay Plus web/API, `ePayPlus/`, shared core

**Why branches first:** lower risk, no duplicate CI/Forge setup, shared migrations stay in one place until you automate subtree sync. Branch separation gives independent release cadence immediately without a big-bang repo split.

## SSH note

Forge branch settings are **UI-only** — SSH cannot change the deploy branch dropdown. After updating Forge, SSH in and verify:

```bash
cd /home/forge/insapos.diybizrewards.com && git branch -vv && grep APP_PRODUCT .env
cd /home/forge/epayplus.diybizrewards.com && git branch -vv && grep APP_PRODUCT .env
```

Each checkout should show `deploy/insa` or `deploy/epayplus` as the tracking branch after the next deploy.
