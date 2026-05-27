# Maya Partner Biller API — ePayPlus Integration

ePayPlus acts as **Partner Biller** in the Maya Bills Payment network. Maya’s consumer app debits the customer; ePayPlus validates the account, posts the bill on the partner side, and sends a **Posting Callback** to confirm fulfillment or failure (refund path).

> **Status:** Validate (Step 1) implemented with fee contract. Post/Inquire/Callback remain scaffolding until Maya onboarding completes.

## User flow (Maya app → Partner)

1. Customer fills payment details in the Maya app and taps **Continue**.
2. Maya creates a bill payment with customer details.
3. Maya PG calls **`POST /api/maya-biller/v1/validate`** on the partner (ePayPlus).
4. Partner validates account, amount, expiry, etc. **No database write on validate.**
5. Partner responds with **`result.code`** and **`fees`** (must match commercial contract; Maya builds the payment slip).
6. Customer reviews/confirms the slip → status **Processing**.
7. Fees may change via Maya RM or optional **`POST /api/maya-biller/v1/fee`** (Get Fee API).

## Public URLs (register with Maya RM)

Base: `{APP_URL}/api/maya-biller/v1`

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/validate` | Step 1 — Validate Bills Payment |
| POST | `/post` | Post Bills Payment (customer debited) |
| POST | `/inquire` | Inquire by Request Reference No |
| POST | `/fee` | Get Fee (optional; same fee shape as validate success) |

Admin: `{APP_URL}/epayplus/integrations/maya`

## Step 1 — Validate request

Headers (required when enabled):

- `Request-Reference-No` — unique per request
- `paymaya-signature` — `Base64(SHA256(rawBody + secretKey))` (confirm with Maya RM)

Example body:

```json
{
  "billerCode": "MERALCO",
  "accountNumber": "1234567890",
  "amount": 1500.00,
  "currency": "PHP",
  "mobileNo": "09171234567",
  "referenceNo": "20260527001",
  "billExpiry": "2026-12-31",
  "data": {}
}
```

Snake_case aliases (`biller_code`, `account_number`, etc.) are accepted.

## Step 1 — Validate success response

HTTP 200. **Fees are mandatory on success.**

```json
{
  "result": {
    "code": "0000"
  },
  "fees": {
    "convenienceFee": 0.00,
    "serviceFee": 15.00,
    "totalFee": 15.00
  }
}
```

| Field | Description |
|-------|-------------|
| `fees.convenienceFee` | Partner convenience fee (PHP) |
| `fees.serviceFee` | Partner service fee (PHP); often from `epay_products.fee` |
| `fees.totalFee` | Sum of convenience + service |

## Step 1 — Validate error response

HTTP 200 (Maya contract). No `fees` key on errors.

```json
{
  "result": {
    "code": "2559",
    "message": "Account Number is invalid"
  }
}
```

## Result codes (Validate / middleware)

| Code | When |
|------|------|
| `0000` | Validation passed; fees included |
| `2559` | Invalid account / unknown biller / blacklist |
| `2596` | Invalid amount, mobile, expiry, or billing data |
| `ACQ018` | Integration disabled, missing RRN, or signature failure |

Other endpoints (post/inquire) may use legacy `resultCode` fields until aligned with Maya RM.

## Fee configuration

File: `config/maya_biller.php`

| Key | Purpose |
|-----|---------|
| `fees.default.convenience_fee` | Fallback convenience fee |
| `fees.default.service_fee` | Fallback service fee |
| `fees.biller_overrides.{CODE}` | Per-biller `convenience_fee` / `service_fee` |
| `fees.contract_note` | Admin reminder to match Maya contract |

Env overrides:

- `MAYA_BILLER_DEFAULT_CONVENIENCE_FEE`
- `MAYA_BILLER_DEFAULT_SERVICE_FEE`
- `MAYA_BILLER_FEE_CONTRACT_NOTE`

**Resolution order:** `fees.biller_overrides` → `epay_products.fee` (BILLS, matched by biller code) → `fees.default`.

`epay_product_pricing` is retailer-specific and is not used for Maya consumer validate.

Service: `App\Services\MayaBiller\MayaBillerFeeService`

## Get Fee API (optional)

`POST /api/maya-biller/v1/fee`

```json
{
  "billerCode": "MERALCO",
  "amount": 1500.00
}
```

Success uses the same `result` + `fees` shape as validate.

## Environment variables

| Variable | Description |
|----------|-------------|
| `MAYA_BILLER_ENABLED` | `true` to accept traffic |
| `MAYA_BILLER_SKIP_SIGNATURE` | `true` for local testing only |
| `MAYA_BILLER_SECRET_KEY` | Inbound signature secret |
| `MAYA_BILLER_CALLBACK_API_KEY` | Outbound callback Basic auth user |
| `MAYA_BILLER_ENVIRONMENT` | `sandbox` or `production` |
| `MAYA_BILLER_DEFAULT_CONVENIENCE_FEE` | Default convenience fee |
| `MAYA_BILLER_DEFAULT_SERVICE_FEE` | Default service fee |

See `.env.example` for full list.

## Security

- Middleware: `MayaBillerSignatureMiddleware`
- Response helper: `App\Support\MayaBiller\MayaBillerResponse`

## Transaction states (post flow)

```
NEW → PROCESSING → AUTHORIZED → POSTING → FULFILLED
                                      └→ POSTING_FAILED
```

Validate does **not** create rows in `epay_maya_biller_transactions`.

## Code map

| Component | Path |
|-----------|------|
| Config | `config/maya_biller.php` |
| Validate service | `app/Services/MayaBiller/MayaBillerValidatePaymentService.php` |
| Fee service | `app/Services/MayaBiller/MayaBillerFeeService.php` |
| Webhook controller | `app/Http/Controllers/Api/MayaBiller/MayaBillerWebhookController.php` |
| Routes | `routes/maya-biller.php` |
| Tests | `tests/Feature/MayaBiller/MayaBillerValidateTest.php`, `tests/Unit/MayaBillerFeeServiceTest.php` |

## Local curl test

Set `MAYA_BILLER_ENABLED=true` and `MAYA_BILLER_SKIP_SIGNATURE=true` for quick tests, or compute signature as below.

```bash
# PowerShell — signature + validate (replace APP_URL and secret)
$body = '{"billerCode":"MERALCO","accountNumber":"1234567890","amount":1500}'
$secret = "your-maya-secret"
$bytes = [System.Text.Encoding]::UTF8.GetBytes($body + $secret)
$hash = [System.Security.Cryptography.SHA256]::Create().ComputeHash($bytes)
$sig = [Convert]::ToBase64String($hash)

curl -X POST "http://localhost/api/maya-biller/v1/validate" `
  -H "Content-Type: application/json" `
  -H "Request-Reference-No: test-rrn-001" `
  -H "paymaya-signature: $sig" `
  -d $body
```

Expected success (with seeded MERALCO BILLS product fee ₱15):

```json
{
  "result": { "code": "0000" },
  "fees": {
    "convenienceFee": 0,
    "serviceFee": 15,
    "totalFee": 15
  }
}
```

## Enabling after Maya onboarding

1. `php artisan migrate`
2. Set env credentials and fee contract values
3. Register URLs with Maya RM
4. Map `biller_code_map` and `fees.biller_overrides` per contract
5. Implement internal posting in `MayaBillerTransactionService::dispatchInternalBillPosting()`
