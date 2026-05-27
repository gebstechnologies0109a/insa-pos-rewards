# Maya Business — scan memory (2026-05-27)

Persistent notes for ePayPlus Maya work. Full artifacts: `C:\Users\Admin\Downloads\Maya-Business-*`.

## Device packages

| Label | Package | Version |
|-------|---------|---------|
| Maya Business | `ph.maya.business.android` | 1.1.0 (1006) |
| Maya Negosyo | `com.paymaya.negosyo` | 2.30.1.365 |
| Maya consumer | `com.paymaya` | (not merchant) |

## Integration paths

1. **Partner Biller** (ePayPlus scaffolded) — `pg.paymaya.com`, inbound validate/post, `epay_maya_biller_transactions`.
2. **Maya Checkout** (scaffold) — `pk-`/`sk-` from `pbm.paymaya.com`, `/checkout/v1/checkouts`, webhooks.
3. **Do not use** `negosyo-api.paymaya.com` — private Negosyo app API.

## OAuth

- Maya Business app: `client_id=mb3`, `connect.paymaya.com`, redirect `mb3://login`.

## RM onboarding

Prioritize Biller form + GPG sandbox; Checkout via MBM Applications for retailer pay-in.

---

## ePayPlus implementation map (2026-05-27)

### Web admin

| URL | Controller | Purpose |
|-----|------------|---------|
| `/epayplus/integrations/maya-negosyo` | `MayaNegosyoIntegrationController` | Merchant hub: Open Negosyo, wallets, Checkout demo, feature grid |
| `/epayplus/integrations/maya` | `MayaBillerIntegrationController` | Partner Biller API endpoints + txn log |

Sidebar: **Integrations → Maya Negosyo**, **Maya Biller**.

### API (Android + tools)

| Method | URL | Auth | Returns |
|--------|-----|------|---------|
| GET | `/api/v2/integrations/maya` | Public | `biller_enabled`, `checkout_enabled`, packages, `deep_link_uri`, `feature_flags` |
| POST | `/api/v2/maya-checkout/sessions` | ePay API token | Checkout session + `redirect_url` |
| POST | `/api/maya-checkout/webhook` | Maya webhook | Updates `epay_maya_checkout_sessions` |

Partner Biller (unchanged): `/api/maya-biller/v1/*`.

### Android 3.2.0 (code 15)

| Surface | Entry |
|---------|--------|
| Home → Quick Services | **Maya Negosyo** tile → `maya-negosyo` route |
| Kiosk home grid | **Maya Negosyo** tile |
| Hub screen | `MayaNegosyoHubScreen.kt` |

Launch: `MayaNegosyoLauncher` → `com.paymaya.negosyo.splash.SplashActivity` → package launcher → `negosyo://` → Play Store.

Manifest `<queries>` for Negosyo/Business packages (Android 11+).

### Config / services

- `config/maya_checkout.php` — Checkout keys (placeholders until RM)
- `config/maya_biller.php` — Partner Biller (existing)
- `MayaIntegrationConfigService` — shared flags for web + API
- `MayaCheckoutService` — demo or live POST to Maya Checkout API

### Credentials vs works now

| Feature | Works now | Needs Maya RM / keys |
|---------|-----------|----------------------|
| Open Negosyo/Business app | Yes (if installed on device) | — |
| ePayPlus hub (wallets, quick links, history) | Yes | — |
| Partner Biller inbound API | Scaffold; enable with env | GPG, sandbox sign-off, `MAYA_BILLER_*` |
| Maya Checkout live | Demo mode only | `MAYA_CHECKOUT_PUBLIC_KEY`, `MAYA_CHECKOUT_SECRET_KEY`, webhook URL registration |
| Settlement reports link | Link to pbm portal | Maya Business Manager access |
