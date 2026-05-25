<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\POS\PosSale;
use App\Models\POS\PosSaleItem;
use App\Models\POS\Product;
use App\Models\POS\Category;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    private bool $isSqlite;

    public function __construct()
    {
        $this->isSqlite = DB::connection()->getDriverName() === 'sqlite';
    }

    private function sqlDate(string $col): string
    {
        return $this->isSqlite ? "strftime('%Y-%m-%d', {$col})" : "DATE({$col})";
    }

    private function sqlHour(string $col): string
    {
        return $this->isSqlite ? "CAST(strftime('%H', {$col}) AS INTEGER)" : "HOUR({$col})";
    }

    public function index()
    {
        return view('backoffice.analytics.index');
    }

    public function data(Request $request): JsonResponse
    {
        $range  = $request->get('range', '7d');
        $from   = $request->get('from');
        $to     = $request->get('to');
        $branch = auth()->user()->branch_id;
        $limit  = min((int) $request->get('top', 10), 100);

        [$start, $end] = $this->resolveRange($range, $from, $to);

        return response()->json([
            'range'      => ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'label' => $range],
            'summary'    => $this->getSummary($branch, $start, $end),
            'realtime'   => $this->getRealtime($branch),
            'daily'      => $this->getDailySales($branch, $start, $end),
            'hourly'     => $this->getHourlySales($branch, $start, $end),
            'payment'    => $this->getPaymentBreakdown($branch, $start, $end),
            'topItems'   => $this->getTopItems($branch, $start, $end, $limit),
            'topCats'    => $this->getTopCategories($branch, $start, $end, $limit),
            'inventory'  => $this->getInventorySnapshot($branch),
        ]);
    }

    public function productDetail(Request $request, int $productId): JsonResponse
    {
        $range  = $request->get('range', '30d');
        $from   = $request->get('from');
        $to     = $request->get('to');
        $branch = auth()->user()->branch_id;

        [$start, $end] = $this->resolveRange($range, $from, $to);

        $product = Product::findOrFail($productId);

        $endOfDay = $end->copy()->endOfDay();
        $dateExpr = $this->sqlDate('pos_sales.created_at');
        $hourExpr = $this->sqlHour('pos_sales.created_at');

        $daily = PosSaleItem::join('pos_sales', 'pos_sale_items.sale_id', '=', 'pos_sales.id')
            ->where('pos_sales.branch_id', $branch)
            ->where('pos_sales.status', 'completed')
            ->where('pos_sale_items.product_id', $productId)
            ->whereBetween('pos_sales.created_at', [$start, $endOfDay])
            ->select(
                DB::raw("{$dateExpr} as date"),
                DB::raw('SUM(pos_sale_items.qty) as qty'),
                DB::raw('SUM(pos_sale_items.line_total) as revenue'),
                DB::raw('SUM(pos_sale_items.discount) as discount'),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $hourly = PosSaleItem::join('pos_sales', 'pos_sale_items.sale_id', '=', 'pos_sales.id')
            ->where('pos_sales.branch_id', $branch)
            ->where('pos_sales.status', 'completed')
            ->where('pos_sale_items.product_id', $productId)
            ->whereBetween('pos_sales.created_at', [$start, $endOfDay])
            ->select(
                DB::raw("{$hourExpr} as hour"),
                DB::raw('SUM(pos_sale_items.qty) as qty'),
                DB::raw('SUM(pos_sale_items.line_total) as revenue'),
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $totals = PosSaleItem::join('pos_sales', 'pos_sale_items.sale_id', '=', 'pos_sales.id')
            ->where('pos_sales.branch_id', $branch)
            ->where('pos_sales.status', 'completed')
            ->where('pos_sale_items.product_id', $productId)
            ->whereBetween('pos_sales.created_at', [$start, $endOfDay])
            ->select(
                DB::raw('SUM(pos_sale_items.qty) as total_qty'),
                DB::raw('SUM(pos_sale_items.line_total) as total_revenue'),
                DB::raw('SUM(pos_sale_items.discount) as total_discount'),
                DB::raw('AVG(pos_sale_items.qty) as avg_qty_per_sale'),
                DB::raw('COUNT(DISTINCT pos_sale_items.sale_id) as sale_count'),
            )
            ->first();

        return response()->json([
            'product' => [
                'id'    => $product->id,
                'name'  => $product->name,
                'sku'   => $product->sku,
                'price' => $product->price,
            ],
            'totals'  => $totals,
            'daily'   => $daily,
            'hourly'  => $hourly,
        ]);
    }

    private function resolveRange(string $range, ?string $from, ?string $to): array
    {
        $end = Carbon::today();

        if ($range === 'custom' && $from && $to) {
            return [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()];
        }

        $map = [
            '1d'  => 0,   '7d'  => 6,   '14d' => 13, '30d' => 29,
            '1m'  => null, '2m'  => null, '3m'  => null,
            '6m'  => null, '12m' => null,
        ];

        if (isset($map[$range])) {
            if ($map[$range] !== null) {
                return [$end->copy()->subDays($map[$range])->startOfDay(), $end->copy()->endOfDay()];
            }
            $months = (int) filter_var($range, FILTER_SANITIZE_NUMBER_INT);
            return [$end->copy()->subMonths($months)->startOfDay(), $end->copy()->endOfDay()];
        }

        return [$end->copy()->subDays(6)->startOfDay(), $end->copy()->endOfDay()];
    }

    private function salesQuery(int $branch, Carbon $start, Carbon $end)
    {
        return PosSale::where('branch_id', $branch)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end->copy()->endOfDay()]);
    }

    private function getSummary(int $branch, Carbon $start, Carbon $end): array
    {
        $row = $this->salesQuery($branch, $start, $end)->select(
            DB::raw('COUNT(*) as tx_count'),
            DB::raw('COALESCE(SUM(total), 0) as revenue'),
            DB::raw('COALESCE(SUM(discount_total), 0) as discounts'),
            DB::raw('COALESCE(AVG(total), 0) as avg_ticket'),
        )->first();

        $prevDays  = max($start->diffInDays($end), 1);
        $prevStart = $start->copy()->subDays($prevDays + 1);
        $prevEnd   = $start->copy()->subDay();
        $prevRev   = PosSale::where('branch_id', $branch)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$prevStart, $prevEnd->copy()->endOfDay()])
            ->sum('total');

        $growth = $prevRev > 0 ? round((($row->revenue - $prevRev) / $prevRev) * 100, 1) : null;

        $itemsSold = PosSaleItem::join('pos_sales', 'pos_sale_items.sale_id', '=', 'pos_sales.id')
            ->where('pos_sales.branch_id', $branch)
            ->where('pos_sales.status', 'completed')
            ->whereBetween('pos_sales.created_at', [$start, $end->copy()->endOfDay()])
            ->sum('pos_sale_items.qty');

        $uniqueCustomers = $this->salesQuery($branch, $start, $end)
            ->whereNotNull('member_id')
            ->distinct('member_id')
            ->count('member_id');

        return [
            'tx_count'         => (int) $row->tx_count,
            'revenue'          => round((float) $row->revenue, 2),
            'discounts'        => round((float) $row->discounts, 2),
            'avg_ticket'       => round((float) $row->avg_ticket, 2),
            'items_sold'       => (int) $itemsSold,
            'unique_customers' => $uniqueCustomers,
            'growth_pct'       => $growth,
        ];
    }

    private function getRealtime(int $branch): array
    {
        $today = Carbon::today();

        $todaySales = PosSale::where('branch_id', $branch)
            ->where('status', 'completed')
            ->whereDate('created_at', $today)
            ->select(
                DB::raw('COUNT(*) as tx_count'),
                DB::raw('COALESCE(SUM(total), 0) as revenue'),
            )->first();

        $openShifts = DB::table('pos_shifts')
            ->where('branch_id', $branch)
            ->whereNull('closed_at')
            ->select('id', 'cashier_id', 'opening_cash', 'opened_at')
            ->get();

        $runningShiftIds = $openShifts->pluck('id');
        $shiftSales = $runningShiftIds->isEmpty() ? 0 :
            PosSale::where('branch_id', $branch)
                ->whereIn('shift_id', $runningShiftIds)
                ->where('status', 'completed')
                ->sum('total');

        $lowStock = 0;
        try {
            $lowStockRows = DB::table('stock_movements')
                ->join('products', 'stock_movements.product_id', '=', 'products.id')
                ->where('stock_movements.branch_id', $branch)
                ->where('products.active', true)
                ->select('stock_movements.product_id', DB::raw('SUM(stock_movements.qty) as total_qty'))
                ->groupBy('stock_movements.product_id')
                ->havingRaw('SUM(stock_movements.qty) <= 10')
                ->get();
            $lowStock = $lowStockRows->count();
        } catch (\Throwable $e) {
            // stock_movements table may not exist yet
        }

        return [
            'today_tx'       => (int) ($todaySales->tx_count ?? 0),
            'today_revenue'  => round((float) ($todaySales->revenue ?? 0), 2),
            'open_shifts'    => $openShifts->count(),
            'running_sales'  => round((float) $shiftSales, 2),
            'low_stock_count'=> $lowStock,
        ];
    }

    private function getDailySales(int $branch, Carbon $start, Carbon $end): array
    {
        $dateExpr = $this->sqlDate('created_at');

        return $this->salesQuery($branch, $start, $end)
            ->select(
                DB::raw("{$dateExpr} as date"),
                DB::raw('COUNT(*) as tx_count'),
                DB::raw('COALESCE(SUM(total), 0) as revenue'),
                DB::raw('COALESCE(SUM(discount_total), 0) as discounts'),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    private function getHourlySales(int $branch, Carbon $start, Carbon $end): array
    {
        $hourExpr = $this->sqlHour('created_at');

        return $this->salesQuery($branch, $start, $end)
            ->select(
                DB::raw("{$hourExpr} as hour"),
                DB::raw('COUNT(*) as tx_count'),
                DB::raw('COALESCE(SUM(total), 0) as revenue'),
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->toArray();
    }

    private function getPaymentBreakdown(int $branch, Carbon $start, Carbon $end): array
    {
        return $this->salesQuery($branch, $start, $end)
            ->select(
                'payment_method',
                DB::raw('COUNT(*) as tx_count'),
                DB::raw('COALESCE(SUM(total), 0) as revenue'),
            )
            ->groupBy('payment_method')
            ->orderByDesc('revenue')
            ->get()
            ->toArray();
    }

    private function getTopItems(int $branch, Carbon $start, Carbon $end, int $limit): array
    {
        return PosSaleItem::join('pos_sales', 'pos_sale_items.sale_id', '=', 'pos_sales.id')
            ->where('pos_sales.branch_id', $branch)
            ->where('pos_sales.status', 'completed')
            ->whereBetween('pos_sales.created_at', [$start, $end->copy()->endOfDay()])
            ->select(
                'pos_sale_items.product_id',
                'pos_sale_items.product_name',
                'pos_sale_items.sku',
                DB::raw('SUM(pos_sale_items.qty) as total_qty'),
                DB::raw('SUM(pos_sale_items.line_total) as total_revenue'),
                DB::raw('SUM(pos_sale_items.discount) as total_discount'),
                DB::raw('COUNT(DISTINCT pos_sale_items.sale_id) as sale_count'),
            )
            ->groupBy('pos_sale_items.product_id', 'pos_sale_items.product_name', 'pos_sale_items.sku')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    private function getTopCategories(int $branch, Carbon $start, Carbon $end, int $limit): array
    {
        return PosSaleItem::join('pos_sales', 'pos_sale_items.sale_id', '=', 'pos_sales.id')
            ->join('products', 'pos_sale_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('pos_sales.branch_id', $branch)
            ->where('pos_sales.status', 'completed')
            ->whereBetween('pos_sales.created_at', [$start, $end->copy()->endOfDay()])
            ->select(
                'categories.id as category_id',
                'categories.name as category_name',
                DB::raw('SUM(pos_sale_items.qty) as total_qty'),
                DB::raw('SUM(pos_sale_items.line_total) as total_revenue'),
                DB::raw('COUNT(DISTINCT pos_sale_items.sale_id) as sale_count'),
                DB::raw('COUNT(DISTINCT pos_sale_items.product_id) as product_count'),
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    private function getInventorySnapshot(int $branch): array
    {
        try {
            return DB::table('stock_movements')
                ->join('products', 'stock_movements.product_id', '=', 'products.id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->where('stock_movements.branch_id', $branch)
                ->where('products.active', true)
                ->select(
                    'products.id', 'products.name', 'products.sku',
                    'categories.name as category',
                    DB::raw('SUM(stock_movements.qty) as stock'),
                )
                ->groupBy('products.id', 'products.name', 'products.sku', 'categories.name')
                ->havingRaw('SUM(stock_movements.qty) <= 10')
                ->orderBy('stock')
                ->limit(20)
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
