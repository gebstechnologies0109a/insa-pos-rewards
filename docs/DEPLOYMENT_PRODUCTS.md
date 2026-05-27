# Deploying INSA POS and ePay Plus from one repository

This monorepo contains **two products** that share Laravel code and a database, but must be deployed and operated as separate offerings:

| Product | Web hostname (production) | Android app | Admin UI |
|---------|---------------------------|-------------|----------|
| **INSA POS** | `insapos.diybizrewards.com` | `INSAPOS/`, `INSAPOSv2/` (INSAPOSv3 app) | Back office, cashier, super-admin (licenses/branches) |
| **ePay Plus** | `epayplus.diybizrewards.com` | `ePayPlus/` | `/epayplus/*` admin, kiosk API (`/api/v2/*`), Maya Biller |

Do **not** deploy the ePay Plus APK to INSA devices (or vice versa). Each APK is configured for its own API base URL and feature set.

## How host gating works

Product mode is controlled by environment variables and optional host lists:

| Variable | Values | Purpose |
|----------|--------|---------|
| `APP_PRODUCT` | `auto` (default), `insa`, `epayplus` | Force a product regardless of hostname, or resolve from host when `auto` |
| `EPAYPLUS_HOSTS` | Comma-separated hostnames | Hosts that run ePay Plus when `APP_PRODUCT=auto` |
| `INSA_HOSTS` | Comma-separated hostnames | Hosts that run INSA when `APP_PRODUCT=auto` |

Resolution when `APP_PRODUCT=auto`:

1. Request host matches `EPAYPLUS_HOSTS` → **epayplus**
2. Else matches `INSA_HOSTS` → **insa**
3. Else hostname contains `epayplus` → **epayplus**
4. Else → **insa**

Middleware (HTTP only; **Artisan/console is never blocked**):

- `insa.product` — INSA web routes (`routes/web.php` POS/backoffice/admin/super-admin) and `api/pos/*`
- `epayplus.product` — ePay Plus web (`routes/epayplus-web.php`), mobile API (`routes/epayplus-api.php`), Maya Biller (`routes/maya-biller.php`), Maya checkout webhook

Wrong product for the current host → **404** (routes are not registered for the other product from the client's perspective; they exist in the route table but abort).

Helpers in Blade and PHP: `current_product()`, `is_epayplus_product()`, `is_insa_product()`.

## Parted deploy branches

INSA and ePay Plus **no longer share a single production deploy branch**. Each Forge site tracks its own branch so releases are independent.

| Forge site | Deploy branch | Script |
|------------|---------------|--------|
| insapos.diybizrewards.com | `deploy/insa` | `scripts/forge-deploy-insa.sh` |
| epayplus.diybizrewards.com | `deploy/epayplus` | `scripts/forge-deploy-epayplus.sh` |

- `main` stays the integration branch; merge `main` → `deploy/insa` or `deploy/epayplus` when that product is ready to ship.
- Full workflow, Forge UI steps, and Phase 2 repo-split notes: [DEPLOYMENT_SEPARATION.md](./DEPLOYMENT_SEPARATION.md).

### Forge UI — change deploy branch (one-time)

For **each** site: **Forge → Site → App → Deployment → Deploy branch**

1. **insapos** — set branch to `deploy/insa`; paste `scripts/forge-deploy-insa.sh` as the deploy script (or append its steps).
2. **epayplus** — set branch to `deploy/epayplus`; paste `scripts/forge-deploy-epayplus.sh`.

Enable **Quick Deploy** only on the branch that site should auto-deploy from. Do not point both sites at `main`.

## Laravel Forge (recommended)

Use **two sites** from the same Git repository, each with its own `.env` and **its own deploy branch** (see above):

### Site A — INSA POS

```env
APP_PRODUCT=insa
APP_URL=https://insapos.diybizrewards.com
INSA_HOSTS=insapos.diybizrewards.com
EPAYPLUS_HOSTS=epayplus.diybizrewards.com
```

Deploy branch: `deploy/insa`. Deploy script: `scripts/forge-deploy-insa.sh` (verifies `APP_PRODUCT=insa`, migrations, cache). No ePay Plus–specific steps.

### Site B — ePay Plus

```env
APP_PRODUCT=epayplus
APP_URL=https://epayplus.diybizrewards.com
EPAYPLUS_HOSTS=epayplus.diybizrewards.com
INSA_HOSTS=insapos.diybizrewards.com
MAYA_BILLER_PUBLIC_BASE_URL=https://epayplus.diybizrewards.com
```

Deploy branch: `deploy/epayplus`. Deploy script: `scripts/forge-deploy-epayplus.sh` (verifies `APP_PRODUCT=epayplus`, migrations, `epay:sync-dual-wallets`, cache).

Configure Maya Biller callbacks and mobile app base URL to this hostname only.

## Local development (Laragon)

- **INSA-only** (e.g. `insapos.test`): set `APP_PRODUCT=insa` so localhost is not treated as ePay Plus.
- **ePay Plus** (this folder): set `APP_PRODUCT=epayplus` and point the vhost at the project root.

Default `EPAYPLUS_HOSTS` includes `localhost` for `auto` mode; override with `APP_PRODUCT` when working on INSA locally.

## What stays shared

- Single Git repo and database (no split in this design)
- Login route (`/login`) on both hosts; post-login redirect is product-aware
- Migrations and seeders run on either deployment (`APP_PRODUCT` does not block Artisan)

## What must not cross over

- Do not link to `epayplus.*` routes from INSA super-admin or back office (gated in views).
- Do not point INSA Android at `epayplus.*` or ePay Plus `/api/v2`.
- Do not point ePay Plus Android at INSA `/api/pos` or cashier URLs.
- Forge deploy hooks for one site should not assume the other site's `APP_PRODUCT`.

## Android artifacts

| Directory | Build output | API prefix |
|-----------|--------------|------------|
| `INSAPOS/`, `INSAPOSv2/` | INSA POS APK | `/api/pos` on INSA host |
| `ePayPlus/` | ePay Plus kiosk APK | `/api/v2` on ePay Plus host |

Release and QA each APK against the matching Forge site only.
