<?php

namespace App\Services\POS;

use App\Models\POS\StockIn;
use App\Models\POS\StockInItem;
use App\Services\Inventory\InventoryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockInService
{
    public function __construct(
        protected InventoryService $inventory,
    ) {}

    public function create(array $data): StockIn
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'];

            $totalCost = 0;

            foreach ($items as $item) {
                $totalCost += ($item['qty'] * $item['cost']);
            }

            $stockIn = StockIn::create([
                'stock_in_number' => $this->generateStockInNumber(),
                'branch_id'       => $data['branch_id'],
                'user_id'         => $data['user_id'],
                'supplier_name'   => $data['supplier_name'] ?? null,
                'total_cost'      => $totalCost,
                'received_at'     => Carbon::now(),
            ]);

            foreach ($items as $item) {
                $lineTotal = $item['qty'] * $item['cost'];

                StockInItem::create([
                    'stock_in_id'  => $stockIn->id,
                    'product_id'   => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'sku'          => $item['sku'] ?? null,
                    'qty'          => $item['qty'],
                    'cost'         => $item['cost'],
                    'line_total'   => $lineTotal,
                ]);
            }

            $this->inventory->stockIn(
                branchId: (int) $data['branch_id'],
                items: collect($items)->map(fn ($item) => [
                    'product_id'  => $item['product_id'],
                    'qty'         => $item['qty'],
                    'cost'        => $item['cost'],
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'batch_code'  => $item['batch_code'] ?? null,
                ])->all(),
                type: 'stock_in',
                referenceId: $stockIn->id,
                referenceNumber: $stockIn->stock_in_number,
                userId: $data['user_id'] ?? null,
                supplierName: $data['supplier_name'] ?? null,
            );

            return $stockIn->load('items');
        });
    }

    protected function generateStockInNumber(): string
    {
        return 'SI' . now()->format('YmdHis') . Str::upper(Str::random(4));
    }
}
