<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$retailer = \App\Models\EPayPlus\Retailer::where('account_id', 'EPDEMO001')->first();
if (!$retailer) {
    fwrite(STDERR, "EPDEMO001 not found\n");
    exit(1);
}

$request = new \Illuminate\Http\Request();
$request->attributes->set('retailer', $retailer);

$balance = app(\App\Http\Controllers\Api\V2\AccountController::class)->balance($request)->getData(true);
$wallets = app(\App\Http\Controllers\Api\V2\WalletController::class)->index($request)->getData(true);

echo json_encode([
    'db' => [
        'balance' => (float) $retailer->balance,
        'eload_balance' => (float) $retailer->eload_balance,
        'bills_balance' => (float) $retailer->bills_balance,
    ],
    'account_balance_api' => $balance,
    'wallets_api' => $wallets,
], JSON_PRETTY_PRINT) . PHP_EOL;
