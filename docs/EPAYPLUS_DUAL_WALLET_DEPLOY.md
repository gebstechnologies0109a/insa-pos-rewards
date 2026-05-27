# ePayPlus dual wallet deploy (E-Load + Bills/Cash-In)

## Production steps

1. Deploy code (includes migration `2026_05_27_120000_sync_retailer_dual_wallet_balances`).

2. Run migrations only (splits legacy balances 70% E-Load / 30% Bills):

   ```bash
   php artisan migrate --force
   ```

3. Optional — re-run split for any retailers missed (safe; skips already-split rows):

   ```bash
   php artisan epay:sync-dual-wallets --dry-run
   php artisan epay:sync-dual-wallets
   ```

4. Optional — refresh demo catalog + EPDEMO001 fixed wallets (does **not** wipe providers/products):

   ```bash
   php artisan db:seed --class=EPayPlusSeeder --force
   ```

## Split rule

- `eload_balance = ROUND(balance × 0.7, 2)`
- `bills_balance = balance − eload_balance`
- Applies when `balance > 0`, `bills_balance ≤ 0`, and wallet is unsplit (`eload_balance ≤ 0` or `eload_balance ≈ balance`).

## Demo retailer (EPDEMO001)

| Field           | Value    |
|-----------------|----------|
| balance         | 10,000   |
| eload_balance   | 7,000    |
| bills_balance   | 3,000    |

## API verification

```bash
# Login
curl -s -X POST https://YOUR_HOST/api/v2/auth/login \
  -H "Content-Type: application/json" \
  -d '{"account_id":"EPDEMO001","pin":"1234","device_id":"deploy-check"}'

# Use token from response
curl -s https://YOUR_HOST/api/v2/account/balance -H "Authorization: Bearer TOKEN"
curl -s https://YOUR_HOST/api/v2/wallets -H "Authorization: Bearer TOKEN"
```

Expected: combined `10000`, eload `7000`, bills `3000`.
