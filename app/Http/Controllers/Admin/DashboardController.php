<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\POS\PosSale;
use App\Models\POS\PosShift;
use App\Models\POS\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $branchId = $request->input('branch_id', $user->branch_id ?? 1);
        $branches = Branch::orderBy('name')->get();

        $totalProducts = Product::where('active', true)->count();

        $salesToday = PosSale::whereDate('sold_at', today())
            ->where('branch_id', $branchId)
            ->count();

        $revenueToday = PosSale::whereDate('sold_at', today())
            ->where('branch_id', $branchId)
            ->sum('total');

        $lowStockCount = DB::table('products')
            ->where('products.active', true)
            ->whereRaw("(SELECT COALESCE(SUM(sm.qty),0) FROM stock_movements sm WHERE sm.product_id = products.id AND sm.branch_id = ?) > 0", [$branchId])
            ->whereRaw("(SELECT COALESCE(SUM(sm.qty),0) FROM stock_movements sm WHERE sm.product_id = products.id AND sm.branch_id = ?) <= 10", [$branchId])
            ->count();

        $outOfStockCount = DB::table('products')
            ->where('products.active', true)
            ->whereRaw("(SELECT COALESCE(SUM(sm.qty),0) FROM stock_movements sm WHERE sm.product_id = products.id AND sm.branch_id = ?) <= 0", [$branchId])
            ->count();

        $totalUsers = User::count();

        $recentSales = PosSale::where('branch_id', $branchId)
            ->orderByDesc('sold_at')
            ->limit(10)
            ->get(['sale_number', 'total', 'sold_at']);

        $todayShiftsQuery = PosShift::whereDate('opened_at', today());
        if ($branchId) {
            $todayShiftsQuery->where('branch_id', $branchId);
        }
        $todayShifts = $todayShiftsQuery->get();

        $shiftSummary = [
            'total'    => $todayShifts->count(),
            'open'     => $todayShifts->where('status', 'open')->count(),
            'closed'   => $todayShifts->where('status', 'closed')->count(),
            'sales'    => $todayShifts->sum('system_sales_total') ?? 0,
            'variance' => $todayShifts->sum('cash_variance') ?? 0,
        ];

        return view('backoffice.dashboard', compact(
            'branches', 'branchId', 'totalProducts', 'salesToday',
            'revenueToday', 'lowStockCount', 'outOfStockCount',
            'totalUsers', 'recentSales', 'shiftSummary',
        ));
    }
}
