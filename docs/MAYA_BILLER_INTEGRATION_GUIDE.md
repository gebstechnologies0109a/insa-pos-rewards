# Maya Partner Biller — ePayPlus Integration Guide

Master onboarding guide for **Create & Develop (Steps 1–4)**, **Sandbox**, and **UAT**. Technical API reference: [`MAYA_BILLER_API.md`](MAYA_BILLER_API.md).

| Environment | Base URL (partner inbound) |
|-------------|----------------------------|
| **Production** | `https://epayplus.diybizrewards.com` |
| **Sandbox** | Same host after RM onboarding; Maya PG sandbox calls your registered production URLs |

Admin dashboard: `https://epayplus.diybizrewards.com/epayplus/integrations/maya`

---

## Step 1: Review

### APIs (Maya → ePayPlus)

| API | Method | Path |
|-----|--------|------|
| Validate Bills Payment | POST | `/api/maya-biller/v1/validate` |
| Post Bills Payment | POST | `/api/maya-biller/v1/post` |
| Inquire Transaction | POST | `/api/maya-biller/v1/inquire` |
| Get Fee (optional) | POST | `/api/maya-biller/v1/fee` |

### Outbound (ePayPlus → Maya)

After internal bill posting completes, ePayPlus sends **Posting Callback** to the `callbackUrl` from the Post request (or Maya PG base + `MAYA_BILLER_CALLBACK_PATH`).

- **Auth:** HTTP Basic — username = `MAYA_BILLER_CALLBACK_API_KEY`, password empty (confirm with Maya RM).
- **Headers:** `Request-Reference-No`, `Content-Type: application/json`.

### Inbound auth

- **Header:** `paymaya-signature` = `Base64(SHA256(rawBody + MAYA_BILLER_SECRET_KEY))`
- **Header:** `Request-Reference-No` (unique per request; same RRN links Validate → Post → Callback)
- **Local only:** `MAYA_BILLER_SKIP_SIGNATURE=true` — never enable in production.

### Lifecycle

```
Maya app: customer enters bill details
    → Validate (stateless, no DB row)
    → Customer confirms slip
    → Post (persist txn, queue posting job)
    → Internal BILLS posting
    → Posting Callback to Maya (FULFILLED or POSTING_FAILED)
```

### State machine (`epay_maya_biller_transactions.state`)

| State | Meaning |
|-------|---------|
| `NEW` | Row created at start of Post |
| `PROCESSING` | Optional intermediate (reserved) |
| `AUTHORIZED` | Post accepted; customer debited at Maya |
| `POSTING` | Internal bill payment in progress |
| `FULFILLED` | Posted successfully; callback sent |
| `POSTING_FAILED` | Posting failed; callback sent (refund path at Maya) |
| `FAILED` | Terminal failure before/during posting |

Validate does **not** create database rows. Post requires a prior successful Validate for the same `Request-Reference-No` (cached proof, TTL configurable).

---

## Step 2: Create endpoints

Register these URLs with your Maya Relationship Manager:

| Field (Maya form) | URL |
|-------------------|-----|
| **Validate URL** | `https://epayplus.diybizrewards.com/api/maya-biller/v1/validate` |
| **Post URL** | `https://epayplus.diybizrewards.com/api/maya-biller/v1/post` |
| **Inquire URL** | `https://epayplus.diybizrewards.com/api/maya-biller/v1/inquire` |
| **Get Fee URL** | `https://epayplus.diybizrewards.com/api/maya-biller/v1/fee` |

Requirements:

- HTTPS, publicly reachable from Maya PG.
- TLS 1.2+.
- Return JSON per Maya contract (see API doc).

---

## Step 3: Local development mock testing

See **[`MAYA_BILLER_TESTING.md`](MAYA_BILLER_TESTING.md)** for:

- `.env` local settings
- PHPUnit commands and last run results
- Postman collection: [`postman/ePayPlus-Maya-Biller-Local-Mock.json`](../postman/ePayPlus-Maya-Biller-Local-Mock.json)

### Minimum mock checklist

- [ ] Validate success `0000` + `fees` object
- [ ] Validate errors `2559` / `2596` / `ACQ018`
- [ ] Get Fee returns same fee shape as Validate
- [ ] Post after Validate returns `202` + `queued: true`
- [ ] Duplicate Post same RRN is idempotent (`200`, `queued: false`)
- [ ] Post without Validate returns `ACQ018`
- [ ] Inquire returns txn status by RRN
- [ ] Signature mismatch returns `ACQ018`
- [ ] PHPUnit suite green: `php artisan test --filter=MayaBiller`

---

## Step 4: Submit onboarding to Maya RM

**Contact:** business.support@maya.ph (cc your assigned Maya RM)

### Email template

```
Subject: ePay Plus — Maya Bills Payment Partner Biller onboarding (Create & Develop)

Hi Maya Team,

We have completed local mock testing for the Partner Biller integration and request onboarding review.

Partner: ePay Plus
Biller display name: ePay Plus

Inbound URLs:
  Validate: https://epayplus.diybizrewards.com/api/maya-biller/v1/validate
  Post:     https://epayplus.diybizrewards.com/api/maya-biller/v1/post
  Inquire:  https://epayplus.diybizrewards.com/api/maya-biller/v1/inquire
  Get Fee:  https://epayplus.diybizrewards.com/api/maya-biller/v1/fee

Attached:
  - Completed Maya Bills Payment Integration Form
  - Mock test evidence (Postman export / PHPUnit summary)

Please advise on sandbox GPG key exchange and UAT schedule.

Regards,
[Name]
[Company]
[Phone]
```

Attach:

1. [`docs/forms/Maya-Bills-Payment-Integration-Form-TEMPLATE.md`](forms/Maya-Bills-Payment-Integration-Form-TEMPLATE.md) (filled PDF/export)
2. Postman run export or screenshot pack
3. [`docs/MAYA_BILLER_TESTING.md`](MAYA_BILLER_TESTING.md) test summary

---

## Sandbox integration

### Step 1: GPG key exchange

- Maya provides sandbox credentials via **GPG-encrypted** email.
- Generate a GPG key pair for your integration contact; share **public key** with Maya RM.
- Store decrypted secrets only in server `.env` — **never commit** `MAYA_BILLER_SECRET_KEY`, `MAYA_BILLER_CALLBACK_API_KEY`, or GPG private keys.

| Variable | Purpose |
|----------|---------|
| `MAYA_BILLER_SECRET_KEY` | Inbound `paymaya-signature` |
| `MAYA_BILLER_CALLBACK_API_KEY` | Outbound callback Basic auth |
| `MAYA_BILLER_ENVIRONMENT=sandbox` | Select Maya PG sandbox base for callbacks |
| `MAYA_BILLER_SANDBOX_BASE_URL` | Default `https://pg-sandbox.paymaya.com` |

### Step 2: Sandbox Postman

Use Maya’s official sandbox collection (from RM) against the URLs above. After sign-off:

```
Subject: ePay Plus — Sandbox sign-off complete

Hi [RM Name],

Sandbox scenarios [list IDs] passed on [date].
Ready for UAT scheduling.

Regards,
[Name]
```

### Step 3: UAT

- Schedule UAT window with Maya RM.
- Run end-to-end: Validate → Post → Callback → Inquire → settlement check in Maya Business Manager.
- Enable production: `MAYA_BILLER_ENABLED=true`, `MAYA_BILLER_SKIP_SIGNATURE=false`, production secrets.

---

## Security summary

| Topic | Guidance |
|-------|----------|
| GPG | Encrypt credential files at rest; rotate per Maya policy |
| `MAYA_BILLER_SECRET_KEY` | `.env` / secrets manager only |
| `MAYA_BILLER_SKIP_SIGNATURE` | Local/dev **only** |
| Callback URL | HTTPS only; validate host allowlist if required by security review |
| Logs | Do not log full account numbers or secrets |

---

## Implementation status

| Area | Status |
|------|--------|
| Validate / Fee / Inquire / Post routes | Implemented |
| Signature middleware | Implemented |
| Fee contract (`MayaBillerFeeService`) | Implemented |
| Post + async posting job + callback | Implemented (BILLS ledger) |
| Maya sandbox/production secrets | **Waiting on Maya RM** |
| Biller code map / commercial fees | Configure after contract |
| Production enable flag | `MAYA_BILLER_ENABLED` (off until UAT) |
