# Maya Biller — Local Mock Testing

Companion to [`MAYA_BILLER_INTEGRATION_GUIDE.md`](MAYA_BILLER_INTEGRATION_GUIDE.md).

---

## Local `.env` (never commit secrets)

```env
MAYA_BILLER_ENABLED=true
MAYA_BILLER_SKIP_SIGNATURE=true
MAYA_BILLER_SECRET_KEY=local-dev-secret
MAYA_BILLER_CALLBACK_API_KEY=local-callback-key
MAYA_BILLER_ENVIRONMENT=sandbox
MAYA_BILLER_SYSTEM_RETAILER=EPDEMO001
```

For signature-accurate tests (PHPUnit default), use `MAYA_BILLER_SKIP_SIGNATURE=false` and `MAYA_BILLER_SECRET_KEY=test-maya-secret` in tests only.

---

## PHPUnit

```powershell
cd "c:\laragon\www\ePay Plus"
php artisan test --filter=MayaBiller
```

### Last run (update after each CI/local run)

| Date | Command | Result |
|------|---------|--------|
| 2026-05-27 | `php artisan test --filter=MayaBiller` | **PASS** — 34 tests (after callback_url migration guard) |

### Suites covered

| Test class | Coverage |
|------------|----------|
| `MayaBillerValidateTest` | Validate, Get Fee, signature, blacklist |
| `MayaBillerPostTest` | Post after validate, idempotency, ACQ018 |
| `MayaBillerInquireTest` | Inquire by RRN |
| `MayaBillerCallbackTest` | Posting job + outbound callback |
| `MayaBillerFeeServiceTest` | Fee resolution |
| `MayaBillerResponseTest` | Response helper |
| `MayaBillerSignatureVerifierTest` | Signature algorithm |
| `MayaBillerStateMachineTest` | State transitions |

---

## Postman

Import: [`postman/ePayPlus-Maya-Biller-Local-Mock.json`](../postman/ePayPlus-Maya-Biller-Local-Mock.json)

Collection variables:

| Variable | Example |
|----------|---------|
| `baseUrl` | `http://epayplus.test` or `https://epayplus.diybizrewards.com` |
| `secretKey` | your `MAYA_BILLER_SECRET_KEY` |
| `requestReferenceNo` | `mock-rrn-{{$timestamp}}` |

Pre-request script computes `paymaya-signature` automatically.

**Flow:** Run **1 Validate** → **2 Post** (same RRN) → **3 Inquire** → optional **Get Fee**.

---

## Manual curl (PowerShell)

```powershell
$base = "http://localhost"
$body = '{"billerCode":"MERALCO","accountNumber":"1234567890","amount":1500}'
$secret = "local-dev-secret"
$bytes = [System.Text.Encoding]::UTF8.GetBytes($body + $secret)
$sig = [Convert]::ToBase64String([System.Security.Cryptography.SHA256]::Create().ComputeHash($bytes))
$rrn = "manual-" + [guid]::NewGuid().ToString("N").Substring(0,12)

Invoke-RestMethod -Method POST -Uri "$base/api/maya-biller/v1/validate" `
  -Headers @{
    "Content-Type" = "application/json"
    "Request-Reference-No" = $rrn
    "paymaya-signature" = $sig
  } -Body $body
```

---

## Minimum pass criteria (mock sign-off)

1. Validate `0000` includes `fees` (serviceFee from product/contract).
2. Invalid account `2559`; invalid amount `2596`.
3. Post without validate `ACQ018`.
4. Post after validate HTTP 202, `queued: true`.
5. Duplicate post HTTP 200, `queued: false`.
6. Inquire returns `status` for existing RRN.
7. All PHPUnit tests in `--filter=MayaBiller` pass.
