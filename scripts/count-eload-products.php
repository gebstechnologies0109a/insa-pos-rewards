<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EPayPlus\Product;

$total = Product::where('type', 'ELOAD')->count();
$withAmount = Product::where('type', 'ELOAD')->where('amount', '>', 0)->count();
$promos = Product::where('type', 'ELOAD')->where('code', 'like', '%_PROMO_%')->count();

echo "ELOAD total: {$total}\n";
echo "ELOAD amount>0: {$withAmount}\n";
echo "ELOAD promos: {$promos}\n\n";

$byProvider = Product::where('epay_products.type', 'ELOAD')
    ->where('epay_products.is_active', true)
    ->join('epay_providers', 'epay_providers.id', '=', 'epay_products.provider_id')
    ->selectRaw('epay_providers.code, count(*) as c')
    ->groupBy('epay_providers.code')
    ->orderBy('epay_providers.code')
    ->get();

foreach ($byProvider as $row) {
    echo "{$row->code}: {$row->c}\n";
}
