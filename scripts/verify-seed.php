<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EPayPlus\Product;
use App\Models\EPayPlus\Provider;

$providers = Provider::selectRaw('type, count(*) as c')->groupBy('type')->pluck('c', 'type');
$eload = Product::where('type', 'ELOAD')->count();
$bills = Product::where('type', 'BILLS')->count();
$ecash = Product::where('type', 'ECASH')->count();
$rfid = Product::where('type', 'RFID')->count();
$telecom = Provider::where('type', 'BILLS')->where('category', 'Telecommunications')->pluck('code');
$eloadInBills = Product::where('type', 'BILLS')->where('amount', '>', 0)->count();
$pldtInEload = Product::where('type', 'ELOAD')->whereHas('provider', fn ($q) => $q->where('code', 'PLDT'))->count();
$prepaidInTelecomBills = Product::where('type', 'BILLS')
    ->whereHas('provider', fn ($q) => $q->where('category', 'Telecommunications')->where('billing_type', 'prepaid'))
    ->count();

echo json_encode([
    'providers' => $providers,
    'products' => ['ELOAD' => $eload, 'BILLS' => $bills, 'ECASH' => $ecash, 'RFID' => $rfid],
    'telecom_billers' => $telecom,
    'eload_denom_in_bills' => $eloadInBills,
    'pldt_in_eload' => $pldtInEload,
    'prepaid_in_telecom_bills' => $prepaidInTelecomBills,
], JSON_PRETTY_PRINT) . PHP_EOL;
