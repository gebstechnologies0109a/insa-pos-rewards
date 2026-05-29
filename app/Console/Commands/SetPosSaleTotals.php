<?php

namespace App\Console\Commands;

use App\Models\POS\PosSale;
use App\Services\POS\PosSaleTotalsResolver;
use Illuminate\Console\Command;

class SetPosSaleTotals extends Command
{
    protected $signature = 'pos:set-sale-totals
                            {sale : Sale number or local_id fragment}
                            {--subtotal= : Header subtotal}
                            {--discount-total= : Header discount_total}
                            {--total= : Header total (register amount)}
                            {--force : Apply without confirmation}';

    protected $description = 'Manually align pos_sales header totals with the register receipt';

    public function handle(PosSaleTotalsResolver $resolver): int
    {
        $sale = PosSale::query()
            ->where('sale_number', 'like', '%' . $this->argument('sale') . '%')
            ->orWhere('local_id', 'like', '%' . $this->argument('sale') . '%')
            ->with('items')
            ->orderByDesc('id')
            ->first();

        if (! $sale) {
            $this->error('Sale not found.');

            return self::FAILURE;
        }

        $data = array_filter([
            'subtotal'       => $this->option('subtotal'),
            'discount_total' => $this->option('discount-total'),
            'total'          => $this->option('total'),
        ], fn ($v) => $v !== null && $v !== '');

        if ($data === []) {
            $this->error('Provide at least one of --subtotal, --discount-total, --total');

            return self::FAILURE;
        }

        $items = $sale->items->map(fn ($i) => [
            'qty'      => $i->qty,
            'price'    => $i->price,
            'discount' => $i->discount,
        ])->all();

        $resolved = $resolver->resolve($items, $data);

        $this->table(
            ['Field', 'Before', 'After'],
            [
                ['subtotal', $sale->subtotal, $resolved['subtotal']],
                ['discount_total', $sale->discount_total, $resolved['discount_total']],
                ['total', $sale->total, $resolved['total']],
            ]
        );

        if (! $this->option('force') && ! $this->confirm('Update sale #' . $sale->id . ' ' . $sale->sale_number . '?', true)) {
            return self::SUCCESS;
        }

        $sale->update([
            'subtotal'       => $resolved['subtotal'],
            'discount_total' => $resolved['discount_total'],
            'total'          => $resolved['total'],
        ]);

        $this->info('Sale totals updated.');

        return self::SUCCESS;
    }
}
