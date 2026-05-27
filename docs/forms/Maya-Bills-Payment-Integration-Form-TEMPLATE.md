# Maya Bills Payment Integration Form — ePay Plus (TEMPLATE)

> Export or copy into Maya’s official PDF form. Replace `[PLACEHOLDER]` values where noted.

---

## Partner information

| Field | Value |
|-------|-------|
| **Legal / registered name** | ePay Plus / [Legal entity name] |
| **Trade / biller display name** | **ePay Plus** |
| **Partner type** | Partner Biller |
| **Website** | https://epayplus.diybizrewards.com |
| **Technical contact name** | [Name] |
| **Technical contact email** | [email@company.com] |
| **Technical contact phone** | [+63 …] |
| **Business contact** | [Name] |
| **Business email** | [email@company.com] |

---

## Inbound API URLs (Partner endpoints)

| Maya form field | URL |
|-----------------|-----|
| **Validate URL** | `https://epayplus.diybizrewards.com/api/maya-biller/v1/validate` |
| **Post URL** | `https://epayplus.diybizrewards.com/api/maya-biller/v1/post` |
| **Inquire URL** | `https://epayplus.diybizrewards.com/api/maya-biller/v1/inquire` |
| **Get Fee URL** (optional) | `https://epayplus.diybizrewards.com/api/maya-biller/v1/fee` |

**HTTP method:** POST (all)  
**Content-Type:** `application/json`

---

## Authentication

| Direction | Mechanism |
|-----------|-----------|
| Maya → Partner (Validate/Post/Inquire/Fee) | Header `paymaya-signature` (SHA-256 + Base64) |
| Partner → Maya (Posting Callback) | HTTP Basic (`MAYA_BILLER_CALLBACK_API_KEY` as username) |
| Correlation | Header `Request-Reference-No` |

---

## Supported billers (initial)

| Maya billerCode | Description | Min amount | Max amount |
|-----------------|-------------|------------|------------|
| MERALCO | Meralco electricity | PHP 1 | PHP 50,000 |
| [Add per contract] | | | |

> Map additional codes in `config/maya_biller.php` → `biller_code_map` and `fees.biller_overrides`.

---

## Fees (commercial contract)

| Fee type | Default (PHP) | Notes |
|----------|---------------|-------|
| Convenience fee | 0.00 | Per biller override |
| Service fee | 5.00 (default) / 15.00 MERALCO from product | Returned on Validate |

---

## IP allowlist / firewall

| Item | Value |
|------|-------|
| Partner egress IPs (callbacks) | [Provide static IPs if applicable] |
| Maya ingress | Allow Maya PG IP ranges per RM document |

---

## Environment

| Environment | Base URL |
|-------------|----------|
| Production partner host | `https://epayplus.diybizrewards.com` |
| Maya PG sandbox | `https://pg-sandbox.paymaya.com` |
| Maya PG production | `https://pg.paymaya.com` |

---

## Attachments checklist

- [ ] Mock test results (Postman / PHPUnit)
- [ ] GPG public key (for sandbox credential delivery)
- [ ] Company registration / NDA if requested by RM

---

## Declaration

We confirm the URLs above are implemented and tested in accordance with the Maya Partner Biller API specification.

**Authorized signatory:** _______________________  
**Title:** _______________________  
**Date:** _______________________
