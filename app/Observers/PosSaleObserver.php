<?php

namespace App\Observers;

use App\Models\POS\PosSale;
use App\Services\POS\PosSaleTotalsResolver;
use Illuminate\Support\Facades\Log;

class PosSaleObserver
{
    public function __construct(
        protected PosSaleTotalsResolver $totals,
    ) {}

    public function saved(PosSale $sale): void
    {
        if (! $sale->wasRecentlyCreated && ! $sale->wasChanged(['subtotal', 'discount_total', 'total'])) {
            return;
        }

        $check = $this->totals->checkSale($sale);

        if (! $check['consistent']) {
            Log::warning('pos_sale_total_inconsistent', [
                'sale_id'     => $sale->id,
                'sale_number' => $sale->sale_number,
                'local_id'    => $sale->local_id,
                'total'       => $sale->total,
                'messages'    => $check['messages'],
            ]);
        }
    }
}
