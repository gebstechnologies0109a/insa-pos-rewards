# ePayPlus device login cheat sheet

Authoritative source: `LoginScreen.kt`, `LoginViewModel.kt`, `ApiModels.kt`, `AuthController.php`, `EPayPlusSeeder.php`. Production API verified 2026-05-27.

## Demo credentials (production)

| Field | Value |
|-------|-------|
| **Account ID** | `EPDEMO001` |
| **PIN** | `1234` |

Seeder stores PIN as `Hash::make('1234')`. Only one demo retailer is seeded (`EPDEMO001`).

## On-device steps

1. Open **ePayPlus** (`com.epayplus.v2` release or `com.epayplus.v2.debug` debug build).
2. On the green **Welcome Back** screen:
   - Tap **Account ID** → enter `EPDEMO001`
   - Tap **PIN** → enter `1234` (4–6 digits, numeric)
   - Tap **Sign In**
3. Success navigates to **Home** (Quick Services / Dual Wallets).

**Do not** look for “Retailer ID” or “Password” — those labels are not on the v2 login screen.

## API login (same credentials)

```bash
curl -s -X POST "https://epayplus.diybizrewards.com/api/v2/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"account_id":"EPDEMO001","pin":"1234"}'
```

JSON keys (from `LoginRequest` / `AuthController`):

- `account_id` — required (NOT `retailer_id`)
- `pin` — required, min 4 chars
- `device_id` — optional

Success response includes `success: true`, `token`, and `account.id`.

Wrong key example: `retailer_id` does not hit the mobile API (returns HTML web login page).

## Fresh install / setup wizard

**Not required** for normal login today.

- `MainActivity` routes directly to `LoginScreen` when logged out.
- `SetupWizardScreen` exists (server URL → license → account → mode) but is **not wired** into navigation.
- Default API base URL is hardcoded: `https://epayplus.diybizrewards.com/api/v2/` (`AppModule.kt`).

After `pm clear`, you should land on the same **Account ID / PIN / Sign In** screen.

## ADB automation (correct script)

Use `scripts/adb-login-epayplus.ps1` — it targets **Account ID**, **PIN**, and **Sign In**.

Avoid legacy patterns in `run-10-txn-tests.ps1` / `run-20-txn-tests.ps1` that search for **Retailer ID** or use blind coordinate taps without label lookup.

## Launch activity

```text
com.epayplus.v2/.ui.MainActivity
com.epayplus.v2.debug/.ui.MainActivity   # debug APK
```
