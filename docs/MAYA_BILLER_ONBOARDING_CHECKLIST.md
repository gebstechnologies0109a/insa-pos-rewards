# Maya Biller Onboarding Checklist — ePay Plus

Printable checklist. Mark items as you complete them.

**Production base:** `https://epayplus.diybizrewards.com`  
**Contact:** business.support@maya.ph

---

## Create & Develop (Steps 1–4)

### Step 1 — Review

- [ ] Read [`MAYA_BILLER_INTEGRATION_GUIDE.md`](MAYA_BILLER_INTEGRATION_GUIDE.md)
- [ ] Read [`MAYA_BILLER_API.md`](MAYA_BILLER_API.md)
- [ ] Understand inbound `paymaya-signature` and outbound Basic callback auth
- [ ] Understand state machine (NEW → AUTHORIZED → POSTING → FULFILLED)
- [ ] Confirm fee contract with commercial / Maya RM

### Step 2 — Endpoints deployed

- [ ] `POST …/api/maya-biller/v1/validate` reachable over HTTPS
- [ ] `POST …/api/maya-biller/v1/post` reachable over HTTPS
- [ ] `POST …/api/maya-biller/v1/inquire` reachable over HTTPS
- [ ] `POST …/api/maya-biller/v1/fee` reachable over HTTPS
- [ ] CSRF excluded for `api/maya-biller/*`
- [ ] `php artisan migrate` run on server
- [ ] Admin page `/epayplus/integrations/maya` shows four URLs

### Step 3 — Local mock testing

- [ ] `.env` local: `MAYA_BILLER_ENABLED=true`, `MAYA_BILLER_SKIP_SIGNATURE=true` (local only)
- [ ] Postman collection imported: `postman/ePayPlus-Maya-Biller-Local-Mock.json`
- [ ] Validate success returns `0000` + fees
- [ ] Validate/Post error codes verified
- [ ] Post after Validate returns 202
- [ ] Inquire returns status for known RRN
- [ ] `php artisan test --filter=MayaBiller` — all pass (see testing doc)

### Step 4 — Submit to Maya RM

- [ ] Fill [`docs/forms/Maya-Bills-Payment-Integration-Form-TEMPLATE.md`](forms/Maya-Bills-Payment-Integration-Form-TEMPLATE.md)
- [ ] Attach mock test evidence
- [ ] Email RM with four URLs (integration guide template)
- [ ] Receive Maya RM acknowledgment / ticket ID: _______________

---

## Sandbox

- [ ] GPG public key generated and sent to Maya
- [ ] Sandbox `MAYA_BILLER_SECRET_KEY` installed in server `.env` (not in git)
- [ ] Sandbox `MAYA_BILLER_CALLBACK_API_KEY` installed
- [ ] `MAYA_BILLER_ENVIRONMENT=sandbox`
- [ ] `MAYA_BILLER_SKIP_SIGNATURE=false` on sandbox server
- [ ] Maya sandbox Postman scenarios passed
- [ ] Sandbox sign-off email sent to RM

---

## UAT & Go-live

- [ ] UAT date scheduled: _______________
- [ ] End-to-end Validate → Post → Callback → Inquire passed
- [ ] Fees match signed commercial contract (`config/maya_biller.php` overrides)
- [ ] `biller_code_map` configured for Maya biller codes
- [ ] Production secrets installed
- [ ] `MAYA_BILLER_ENABLED=true` on production
- [ ] `MAYA_BILLER_SKIP_SIGNATURE=false` on production
- [ ] Monitoring/alerts for `POSTING_FAILED` and callback errors
- [ ] Maya production sign-off received

---

## Sign-off

| Role | Name | Date | Signature |
|------|------|------|-----------|
| Technical lead | | | |
| Operations | | | |
| Maya RM | | | |
