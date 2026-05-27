# ePayPlus device login cheat sheet

Authoritative source: `LoginScreen.kt`, `LoginViewModel.kt`, `ApiModels.kt`, `AuthController.php`, `EPayPlusSeeder.php`. Production API verified 2026-05-28.

## Demo credentials (production)

| Field | Value |
|-------|-------|
| **Mobile Number** | `09171234567` |
| **PIN** | `1234` |
| **Account ID** (internal / admin only) | `EPDEMO001` |

Seeder stores PIN as `Hash::make('1234')` and mobile `09171234567` on retailer `EPDEMO001`.

## On-device steps

1. Open **ePayPlus** (`com.epayplus.v2` release or `com.epayplus.v2.debug` debug build).
2. On the green **Welcome Back** screen:
   - Tap **Mobile Number** → enter `09171234567` (or `+639171234567`)
   - Tap **PIN** → enter `1234` (4–6 digits, numeric)
   - Tap **Sign In**
3. Success navigates to **Home** (Quick Services / Dual Wallets).

**Do not** look for “Account ID” on the login screen — retailers sign in with **mobile + PIN**. Admin web still uses email/password.

## API login (same credentials)

```bash
curl -s -X POST "https://epayplus.diybizrewards.com/api/v2/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"mobile_number":"09171234567","pin":"1234","device_id":"deploy-check"}'
```

JSON keys (from `LoginRequest` / `AuthController`):

- `mobile_number` — required for app login (Philippine format; `+63` accepted)
- `pin` — required, min 4 chars
- `device_id` — optional
- `account_id` — **deprecated**; still accepted for backward compatibility only

Success response includes `success: true`, `token`, and `account.id` (retailer account ID, e.g. `EPDEMO001`).

Wrong key example: `retailer_id` does not hit the mobile API (returns HTML web login page).

## Fresh install / setup wizard

**Not required** for normal login today.

- `MainActivity` routes directly to `LoginScreen` when logged out.
- `SetupWizardScreen` exists (server URL → license → mobile + PIN → mode) but is **not wired** into navigation.
- Default API base URL is hardcoded: `https://epayplus.diybizrewards.com/api/v2/` (`AppModule.kt`).

After `pm clear`, you should land on the same **Mobile Number / PIN / Sign In** screen.

## ADB automation (correct script)

Use `scripts/adb-login-epayplus.ps1` — it targets **Mobile Number**, **PIN**, and **Sign In**.

Avoid legacy patterns in `run-10-txn-tests.ps1` / `run-20-txn-tests.ps1` that search for **Retailer ID** or use blind coordinate taps without label lookup.

## Launch activity

```text
com.epayplus.v2/.ui.MainActivity
com.epayplus.v2.debug/.ui.MainActivity   # debug APK
```
