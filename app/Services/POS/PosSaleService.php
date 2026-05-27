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

            $subtotal = 0;
            $itemDiscountTotal = 0;

            foreach ($items as $item) {
                $lineSubtotal = $item['qty'] * $item['price'];
                $lineDiscount = $item['discount'] ?? 0;
                $subtotal += $lineSubtotal;
                $itemDiscountTotal += $lineDiscount;
            }

            $orderDiscount = (float) ($data['order_discount'] ?? 0);
            $discountTotal = $itemDiscountTotal + $orderDiscount;
            $total = $subtotal - $discountTotal;

            $sale = PosSale::create([
                'sale_number'     => $this->generateSaleNumber(),
                'branch_id'       => $data['branch_id'],
                'shift_id'        => $data['shift_id'] ?? null,
                'cashier_id'      => $data['cashier_id'],
                'member_id'       => $data['member_id'] ?? null,
                'subtotal'        => $subtotal,
                'discount_total'  => $discountTotal,
                'total'           => $total,
                'payment_method'  => $data['payment_method'],
                'amount_tendered' => $data['amount_tendered'],
                'change_due'      => max(0, $data['amount_tendered'] - $total),
                'status'          => 'completed',
                'sold_at'         => Carbon::now(),
            ]);

            foreach ($items as $item) {
                $lineSubtotal = $item['qty'] * $item['price'];
                $lineDiscount = $item['discount'] ?? 0;
                $lineTotal = $lineSubtotal - $lineDiscount;

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

    protected function generateSaleNumber(): string
    {
        return 'S' . now()->format('YmdHis') . Str::upper(Str::random(4));
    }
}
