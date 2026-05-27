<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Api\V2\ProductController;
use Illuminate\Http\Request;

$controller = app(ProductController::class);
$response = $controller->eloadProducts(Request::create('/api/v2/products/eload', 'GET'));
$data = json_decode($response->getContent(), true);
$products = $data['products'] ?? [];

echo 'success=' . ($data['success'] ? 'true' : 'false') . ' count=' . count($products) . PHP_EOL;

$byProvider = [];
foreach ($products as $p) {
    $code = $p['providerCode'] ?? '?';
    $byProvider[$code] = ($byProvider[$code] ?? 0) + 1;
}
ksort($byProvider);
foreach ($byProvider as $code => $count) {
    echo "  {$code}: {$count}\n";
}

$promos = array_filter($products, fn ($p) => ($p['productKind'] ?? '') === 'promo' || ($p['category'] ?? '') === 'Promo');
echo 'promos in API: ' . count($promos) . PHP_EOL;

$sample = array_slice($products, 0, 2);
echo PHP_EOL . 'sample: ' . json_encode($sample, JSON_PRETTY_PRINT) . PHP_EOL;
