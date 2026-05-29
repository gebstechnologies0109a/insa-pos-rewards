<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Concerns\ResolvesInventoryBranch;
use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Services\Reports\PosReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use ResolvesInventoryBranch;

    public function __construct(
        protected PosReportService $reports,
    ) {}

    public function dailySales(Request $request)
    {
        $branchId = $this->resolveInventoryBranchId($request);
        $this->authorizeInventoryBranch($branchId);

        $from = Carbon::parse($request->input('date_from', now()->subDays(30)->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('date_to', now()->toDateString()))->endOfDay();

        $rows = $this->reports->dailySales($branchId, $from, $to);
        $totals = [
            'transactions' => $rows->sum('transaction_count'),
            'revenue'      => $rows->sum('revenue'),
            'discounts'    => $rows->sum('discounts'),
        ];

        $branches = auth()->user()->isBranchScoped()
            ? collect()
            : Branch::orderBy('name')->get();

        return view('backoffice.reports.daily-sales', compact(
            'rows', 'totals', 'branches', 'branchId', 'from', 'to',
        ));
    }

    public function productPerformance(Request $request)
    {
        $branchId = $this->resolveInventoryBranchId($request);
        $this->authorizeInventoryBranch($branchId);

        $from = Carbon::parse($request->input('date_from', now()->subDays(30)->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('date_to', now()->toDateString()))->endOfDay();
        $limit = min(max((int) $request->input('limit', 50), 10), 200);

        $rows = $this->reports->productPerformance($branchId, $from, $to, $limit);
        $totals = [
            'qty_sold' => $rows->sum('qty_sold'),
            'revenue'  => $rows->sum('revenue'),
        ];

        $branches = auth()->user()->isBranchScoped()
            ? collect()
            : Branch::orderBy('name')->get();

        return view('backoffice.reports.product-performance', compact(
            'rows', 'totals', 'branches', 'branchId', 'from', 'to', 'limit',
        ));
    }
}
