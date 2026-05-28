<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sale = \App\Models\POS\PosSale::query()
    ->where(function ($q) {
        $q->where('sale_number', 'like', '%140256%')
            ->orWhere('sale_number', 'like', 'S20260528140256%')
            ->orWhere('local_id', 'like', '%140256%')
            ->orWhere('local_id', 'like', '%VWFS%');
    })
    ->with('items')
    ->orderByDesc('id')
    ->first();

if (! $sale) {
    $byTotal = \App\Models\POS\PosSale::query()
        ->whereBetween('total', [11031, 11032])
        ->orderByDesc('id')
        ->limit(5)
        ->get(['id', 'sale_number', 'total', 'sold_at', 'local_id']);
    echo "NOT FOUND by number. Sales with total ~11031.86:\n";
    foreach ($byTotal as $r) {
        echo "  {$r->sale_number} total={$r->total} sold_at={$r->sold_at} local={$r->local_id}\n";
    }
    $recent = \App\Models\POS\PosSale::query()
        ->orderByDesc('id')
        ->limit(3)
        ->get(['id', 'sale_number', 'total', 'sold_at']);
    echo "Latest sales:\n";
    foreach ($recent as $r) {
        echo "  {$r->sale_number} total={$r->total} sold_at={$r->sold_at}\n";
    }
    exit(1);
}

echo "sale_number={$sale->sale_number}\n";
echo "local_id={$sale->local_id}\n";
echo "subtotal={$sale->subtotal}\n";
echo "discount_total={$sale->discount_total}\n";
echo "total={$sale->total}\n";
echo "amount_tendered={$sale->amount_tendered}\n";
echo "items_count=" . $sale->items->count() . "\n";

$sumLines = 0;
$sumSub = 0;
$sumDisc = 0;
foreach ($sale->items as $i) {
    $lineSub = (float) $i->qty * (float) $i->price;
    $sumSub += $lineSub;
    $sumDisc += (float) $i->discount;
    $sumLines += (float) $i->line_total;
    echo "  - {$i->product_name} qty={$i->qty} price={$i->price} disc={$i->discount} line={$i->line_total}\n";
}

echo "computed_subtotal={$sumSub}\n";
echo "computed_item_discount={$sumDisc}\n";
echo "sum_line_totals={$sumLines}\n";
echo "computed_total_from_items=" . round($sumSub - $sumDisc, 2) . "\n";
