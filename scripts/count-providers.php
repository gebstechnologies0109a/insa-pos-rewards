<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EPayPlus\Product;
use App\Models\EPayPlus\Provider;

echo 'providers=' . Provider::count() . PHP_EOL;
foreach (['ELOAD', 'BILLS', 'ECASH', 'RFID'] as $type) {
    echo $type . '=' . Provider::where('type', $type)->count() . PHP_EOL;
}
echo 'products=' . Product::count() . PHP_EOL;
