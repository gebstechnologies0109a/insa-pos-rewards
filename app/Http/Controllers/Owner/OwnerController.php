<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\POS\PosSale;
use App\Models\POS\PosShift;
use App\Models\POS\Product;
use App\Models\User;
use App\Services\Reports\PosReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OwnerController extends Controller
{
    public function __construct(
        protected PosReportService $reports,
    ) {}

    public function dashboard(Request $request)
    {
        $branchId = (int) $request->input('branch_id', auth()->user()->branch_id ?? 1);
        $branches = Branch::with('company')->orderBy('name')->get();

        $salesToday = PosSale::where('branch_id', $branchId)
            ->where('status', 'completed')
            ->whereDate('sold_at', today())
            ->count();

        $revenueToday = PosSale::where('branch_id', $branchId)
            ->where('status', 'completed')
            ->whereDate('sold_at', today())
            ->sum('total');

        $revenueMonth = PosSale::where('branch_id', $branchId)
            ->where('status', 'completed')
            ->where('sold_at', '>=', now()->startOfMonth())
            ->sum('total');

        $openShifts = PosShift::where('branch_id', $branchId)->where('status', 'open')->count();
        $activeProducts = Product::where('active', true)->count();
        $staffCount = User::where('branch_id', $branchId)->count();

        $lowStockCount = DB::table('products')
            ->where('products.active', true)
            ->whereRaw('(SELECT COALESCE(SUM(sm.qty),0) FROM stock_movements sm WHERE sm.product_id = products.id AND sm.branch_id = ?) > 0', [$branchId])
            ->whereRaw('(SELECT COALESCE(SUM(sm.qty),0) FROM stock_movements sm WHERE sm.product_id = products.id AND sm.branch_id = ?) <= 10', [$branchId])
            ->count();

        $dailySales = $this->reports->dailySales(
            $branchId,
            Carbon::now()->subDays(6)->startOfDay(),
            Carbon::now()->endOfDay(),
        );

        $topProducts = $this->reports->productPerformance(
            $branchId,
            Carbon::now()->subDays(30)->startOfDay(),
            Carbon::now()->endOfDay(),
            5,
        );

        return view('owner.dashboard', compact(
            'branches',
            'branchId',
            'salesToday',
            'revenueToday',
            'revenueMonth',
            'openShifts',
            'activeProducts',
            'staffCount',
            'lowStockCount',
            'dailySales',
            'topProducts',
        ));
    }
}
