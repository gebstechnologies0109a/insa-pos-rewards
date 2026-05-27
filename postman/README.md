# ePayPlus Postman Collections

## Maya Biller — Local Mock

**File:** [`ePayPlus-Maya-Biller-Local-Mock.json`](ePayPlus-Maya-Biller-Local-Mock.json)

### Import

1. Postman → **Import** → select the JSON file.
2. Open collection **variables** and set:
   - `baseUrl` — e.g. `http://epayplus.test` (Laragon) or `https://epayplus.diybizrewards.com`
   - `secretKey` — matches `MAYA_BILLER_SECRET_KEY` (or use with `MAYA_BILLER_SKIP_SIGNATURE=true` and any signature when testing locally)

### Run order

1. **Validate Bills Payment** — creates validate proof for RRN.
2. **Post Bills Payment** — uses same `requestReferenceNo`; include `callbackUrl`.
3. **Inquire Transaction**
4. **Get Fee** (optional)

Collection pre-request script sets `paymaya-signature` = Base64(SHA256(body + secretKey)).

### Sandbox

After Maya RM provides the official sandbox collection, run it against the production-registered URLs listed in [`docs/MAYA_BILLER_INTEGRATION_GUIDE.md`](../docs/MAYA_BILLER_INTEGRATION_GUIDE.md).

Do not commit real `secretKey` values in exported environments.
