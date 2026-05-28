<?php

namespace App\Services\POS;

use App\Events\POS\SaleCompleted;
use App\Models\POS\PosSale;
use App\Models\POS\PosSaleItem;
use App\Services\Inventory\InventoryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosSaleService
{
    public function __construct(
        protected InventoryService $inventory,
        protected PosSaleTotalsResolver $totals,
    ) {}

    public function createSale(array $data): PosSale
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'];

            foreach ($items as $item) {
                $stock = $this->inventory->getStockOnHand($data['branch_id'], $item['product_id']);

                if ($stock < $item['qty']) {
                    throw new \Exception("Insufficient stock for product ID {$item['product_id']}");
                }
            }

            $resolved = $this->totals->resolve($items, $data);

            $sale = PosSale::create([
                'sale_number'     => $this->generateSaleNumber(),
                'branch_id'       => $data['branch_id'],
                'shift_id'        => $data['shift_id'] ?? null,
                'cashier_id'      => $data['cashier_id'],
                'member_id'       => $data['member_id'] ?? null,
                'subtotal'        => $resolved['subtotal'],
                'discount_total'  => $resolved['discount_total'],
                'total'           => $resolved['total'],
                'payment_method'  => $data['payment_method'],
                'amount_tendered' => $data['amount_tendered'],
                'change_due'      => max(0, (float) $data['amount_tendered'] - $resolved['total']),
                'status'          => 'completed',
                'sold_at'         => isset($data['created_at'])
                    ? Carbon::parse($data['created_at'])
                    : Carbon::now(),
            ]);

            foreach ($items as $item) {
                $lineSubtotal = (float) $item['qty'] * (float) $item['price'];
                $lineDiscount = (float) ($item['discount'] ?? 0);
                $lineTotal = round($lineSubtotal - $lineDiscount, 2);

                PosSaleItem::create([
                    'sale_id'      => $sale->id,
                    'product_id'   => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'sku'          => $item['sku'] ?? null,
                    'barcode'      => $item['barcode'] ?? null,
                    'qty'          => $item['qty'],
                    'price'        => $item['price'],
                    'discount'     => $lineDiscount,
                    'line_total'   => $lineTotal,
                ]);

                $this->inventory->stockOut(
                    branchId: (int) $data['branch_id'],
                    productId: (int) $item['product_id'],
                    qty: (float) $item['qty'],
                    type: 'sale',
                    referenceId: $sale->id,
                    referenceNumber: $sale->sale_number,
                    userId: isset($data['cashier_id']) ? (int) $data['cashier_id'] : null,
                    shiftId: isset($data['shift_id']) ? (int) $data['shift_id'] : null,
                );
            }

            $sale->load('items');

            event(new SaleCompleted($sale));

            return $sale;
        });
    }

    /**
     * Align header totals on an already-synced sale when the register re-pushes corrected amounts.
     *
     * @param  array<string, mixed>  $data
     */
    public function reconcileHeaderTotals(PosSale $sale, array $data): PosSale
    {
        $items = $data['items'] ?? [];
        if ($items === []) {
            return $sale;
        }

        $resolved = $this->totals->resolve($items, $data);

        if (abs((float) $sale->total - $resolved['total']) <= PosSaleTotalsResolver::TOLERANCE
            && abs((float) $sale->subtotal - $resolved['subtotal']) <= PosSaleTotalsResolver::TOLERANCE) {
            return $sale;
        }

        $sale->update([
            'subtotal'       => $resolved['subtotal'],
            'discount_total' => $resolved['discount_total'],
            'total'          => $resolved['total'],
            'change_due'     => max(0, (float) ($data['amount_tendered'] ?? $sale->amount_tendered) - $resolved['total']),
        ]);

        return $sale->fresh('items');
    }

    protected function generateSaleNumber(): string
    {
        return 'S' . now()->format('YmdHis') . Str::upper(Str::random(4));
    }
}
