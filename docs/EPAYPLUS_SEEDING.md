# ePayPlus product seeding

After deploying migrations, seed the full prepaid catalog (13 E-Load networks, standard denoms, promos, bills, e-cash, RFID):

```bash
php artisan migrate
php artisan db:seed --class=EPayPlusSeeder
```

**Production** (`https://epayplus.diybizrewards.com`):

```bash
php artisan db:seed --class=EPayPlusSeeder
```

The seeder is idempotent: products are upserted by unique `code` (e.g. `GLOBE_50`, `GLOBE_PROMO_GO50`).

Verify counts locally:

```bash
php scripts/count-eload-products.php
```

E-Load API (requires retailer auth token): `GET /api/v2/products/eload`
