# Maya Negosyo provider catalog (ePayPlus seed)

**Scan date:** 2026-05-27  
**App:** `com.paymaya.negosyo` v2.30.1.365  
**Sources:** `maya-negosyo-app.apk` (offline strings), `maya-negosyo-urls.txt`, `docs/DAFOX_PROVIDER_CATALOG.md` (portal `/promos` + `/bill_fees`), `docs/MAYA_BUSINESS_SCAN_MEMORY.md`

**Do not call** `negosyo-api.paymaya.com` — biller list is loaded at runtime in Negosyo; only one asset slug (`ABSCBNMOB`) is embedded in the APK.

## Seeded counts (after `EPayPlusSeeder`)

| Type | Before supplement | Added (Maya Negosyo) | After seed |
|------|-------------------|----------------------|------------|
| ELOAD | 13 | 0 | 13 |
| BILLS | 33 | 74 | 107 |
| ECASH | 28 | 1 | 29 |
| RFID | 7 | 0 | 7 |
| **Total** | **81** | **75** | **156** |

## Maya Negosyo supplement (new codes)

### APK-confirmed

| Code | Name | Type |
|------|------|------|
| `ABSCBNMOB` | ABS-CBN Mobile | BILLS |

### DaFox / portal gap

| Code | Name | Type |
|------|------|------|
| `BAYANTEL` | Bayantel | BILLS |
| `GCASH_PERA_OUTLET` | GCash Pera Outlet | ECASH |

### High-traffic Pay Bills categories (portal `/bill_fees` + Negosyo merchant flows)

**Electricity (19):** DAVAOLIGHT, BENECO, CEPALCO, ANGELES_ELECTRIC, PENELCO, DANECO, CEBECO1–3, PELCO1–2, SFELAPCO, FLECO, NEECO1, NEECO2_AREA1, QUEZELCO1–2, DECORP, ZAMCELCO  

**Water (6):** LAGUNAWATER, BORACAYWATER, CLARKWATER, LAGUNA_WATER_DISTRICT, BP_WATERWORKS, STA_LUCIA_WATER  

**Internet/Cable (5 + ABSCBNMOB):** STREAMTECH, CABLELINK, GALAXY_CABLE, NOW_CORP, PARASAT  

**Government (9):** DFA, LTO, PSA, BIR, LTFRB, MARINA, PEZA, TIEZA, MYEG  

**Insurance (6):** INSULAR_LIFE, GENERALI, COCOLIFE, PARAMOUNT, STANDARD_INSURANCE, PHILLIFE  

**Loans (10):** TONIK, CASHALO, AEON, TALA, UNIONDIGITAL, SKYPAY_LOAN, SB_FINANCE, CHINATRUST_LOAN, GLOBAL_DOMINION, ASIALINK  

**Credit cards (6):** CHINABANK_CC, AUB_CC, SECURITYBANK_CC, UNIONBANK_CC, ROBINSONSBANK_CC, BOC_CC  

**Transportation:** BEEP  

**Travel:** PAL, CEBUPACIFIC, AIRASIA  

**Payment services:** DRAGONPAY, PESOPAY, MULTIPAY  

**Education:** PHINMA_EDUCATION, MAPUA  

**Real estate:** BRIA_HOMES, AVIDA  

## Products

- **ELOAD:** Denominations for all 13 prepaid networks (₱10–₱1000).
- **BILLS:** `{CODE}_PAY` fee row per biller (₱15 fee / ₱5 commission).
- **ECASH:** `{CODE}_CASHIN` + tier rows ₱100–₱5000 per wallet.
- **RFID:** `{CODE}_RELOAD` + tier rows ₱100–₱1000 per RFID brand.

## Icons

`provider_code_to_slug()` maps new codes to nearest existing `ic_provider_*` asset. Run `python scripts/sync-provider-icons-from-apk.py` after adding Android drawables.

## Production seed

```bash
cd /path/to/ePayPlus
php artisan db:seed --class=EPayPlusSeeder
php artisan tinker --execute="echo App\Models\EPayPlus\Provider::count();"
```
