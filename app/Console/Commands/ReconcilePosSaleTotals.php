<?php

namespace App\Console\Commands;

use App\Models\POS\PosSale;
use App\Services\POS\PosSaleTotalsResolver;
use Illuminate\Console\Command;

class ReconcilePosSaleTotals extends Command
{
    protected $signature = 'pos:reconcile-sale-totals
                            {--sale= : Sale number or local_id fragment}
                            {--fix : Update pos_sales totals from line items when only header is wrong}
                            {--limit=500 : Max sales to scan when no --sale}';

    protected $description = 'Detect (and optionally fix) pos_sales rows whose total does not match line items';

    public function handle(PosSaleTotalsResolver $resolver): int
    {
        $saleFilter = $this->option('sale');
        $fix = (bool) $this->option('fix');

        $query = PosSale::query()->with('items')->orderByDesc('id');
        if ($saleFilter) {
            $query->where(function ($q) use ($saleFilter) {
                $q->where('sale_number', 'like', "%{$saleFilter}%")
                    ->orWhere('local_id', 'like', "%{$saleFilter}%");
            });
        } else {
            $query->limit((int) $this->option('limit'));
        }

        $inconsistent = 0;
        $fixed = 0;

        $query->chunkById(100, function ($sales) use ($resolver, $fix, &$inconsistent, &$fixed) {
            foreach ($sales as $sale) {
                $check = $resolver->checkSale($sale);
                if ($check['consistent']) {
                    continue;
                }

                $inconsistent++;
                $this->warn("Sale #{$sale->id} {$sale->sale_number} total={$sale->total}");
                foreach ($check['messages'] as $message) {
                    $this->line("  - {$message}");
                }

                if (! $fix) {
                    continue;
                }

                $items = $sale->items->map(fn ($i) => [
                    'qty'      => $i->qty,
                    'price'    => $i->price,
                    'discount' => $i->discount,
                ])->all();

                $fromItems = $resolver->computeFromItems($items);
                $lineTotalSum = $fromItems['line_total_sum'];

                if (abs($lineTotalSum - (float) $sale->total) <= PosSaleTotalsResolver::TOLERANCE) {
                    continue;
                }

                $sale->update([
                    'subtotal'       => $fromItems['subtotal'],
                    'discount_total' => round($fromItems['subtotal'] - $lineTotalSum, 2),
                    'total'          => $lineTotalSum,
                ]);
                $fixed++;
                $this->info("  Fixed header totals from line items → total={$lineTotalSum}");
            }
        });

        $this->info("Inconsistent: {$inconsistent}" . ($fix ? ", fixed: {$fixed}" : ''));

        return $inconsistent > 0 ? self::FAILURE : self::SUCCESS;
    }
}
