<?php

namespace App\Services\Reports;

use App\Models\Inventory\InventoryBatch;
use App\Models\POS\PosSale;
use App\Models\POS\PosSaleItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PosReportService
{
    public function dailySales(int $branchId, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from ??= now()->subDays(30)->startOfDay();
        $to ??= now()->endOfDay();

        $driver = DB::connection()->getDriverName();
        $dateExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', sold_at)"
            : 'DATE(sold_at)';

        return PosSale::query()
            ->where('branch_id', $branchId)
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$from, $to])
            ->selectRaw("{$dateExpr} as sale_date, COUNT(*) as transaction_count, SUM(total) as revenue, SUM(discount_total) as discounts")
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get()
            ->map(fn ($row) => [
                'date'               => $row->sale_date,
                'transaction_count'  => (int) $row->transaction_count,
                'revenue'            => (float) $row->revenue,
                'discounts'          => (float) $row->discounts,
            ]);
    }

    /**
     * @return Collection<int, array{product_id:int, name:string, sku:?string, qty_sold:float, revenue:float, avg_price:float}>
     */
    public function productPerformance(int $branchId, ?Carbon $from = null, ?Carbon $to = null, int $limit = 50): Collection
    {
        $from ??= now()->subDays(30)->startOfDay();
        $to ??= now()->endOfDay();

        return PosSaleItem::query()
            ->join('pos_sales', 'pos_sale_items.sale_id', '=', 'pos_sales.id')
            ->where('pos_sales.branch_id', $branchId)
            ->where('pos_sales.status', 'completed')
            ->whereBetween('pos_sales.sold_at', [$from, $to])
            ->selectRaw('pos_sale_items.product_id, pos_sale_items.product_name as name, pos_sale_items.sku, SUM(pos_sale_items.qty) as qty_sold, SUM(pos_sale_items.qty * pos_sale_items.price) as revenue')
            ->groupBy('pos_sale_items.product_id', 'pos_sale_items.product_name', 'pos_sale_items.sku')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'name'       => $row->name,
                'sku'        => $row->sku,
                'qty_sold'   => (float) $row->qty_sold,
                'revenue'    => (float) $row->revenue,
                'avg_price'  => $row->qty_sold > 0 ? round($row->revenue / $row->qty_sold, 2) : 0,
            ]);
    }

    /**
     * @return Collection<int, array{product_id:int, name:string, sku:?string, on_hand:float, batch_value:float, oldest_expiry:?string, days_in_stock:?int}>
     */
    public function inventoryAging(int $branchId, int $limit = 100): Collection
    {
        return InventoryBatch::query()
            ->with('product:id,name,sku')
            ->where('branch_id', $branchId)
            ->where('quantity', '>', 0)
            ->orderBy('received_at')
            ->limit($limit)
            ->get()
            ->groupBy('product_id')
            ->map(function ($batches) {
                $first = $batches->first();
                $product = $first->product;
                $onHand = $batches->sum(fn ($b) => (float) $b->quantity);
                $value = $batches->sum(fn ($b) => (float) $b->quantity * (float) ($b->cost_price ?? 0));
                $oldest = $batches->min('received_at');

                return [
                    'product_id'     => (int) $first->product_id,
                    'name'           => $product->name ?? '—',
                    'sku'            => $product->sku ?? null,
                    'on_hand'        => $onHand,
                    'batch_value'    => round($value, 2),
                    'oldest_expiry'  => $batches->whereNotNull('expiry_date')->min('expiry_date')?->toDateString(),
                    'days_in_stock'  => $oldest ? (int) $oldest->diffInDays(now()) : null,
                ];
            })
            ->values()
            ->sortByDesc('batch_value')
            ->values();
    }
}
