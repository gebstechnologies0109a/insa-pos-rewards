<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Device;
use App\Models\EPayPlus\DeviceAlert;
use App\Models\EPayPlus\Retailer;
use App\Models\EPayPlus\Transaction;
use App\Models\EPayPlus\Topup;
use App\Models\EPayPlus\Provider;
use App\Models\EPayPlus\Product;
use App\Models\EPayPlus\AuditLog;
use App\Support\ManilaDateRange;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'totalRetailers'     => Retailer::count(),
            'activeRetailers'    => Retailer::where('is_active', true)->count(),
            'todayTransactions'  => Transaction::today()->count(),
            'todaySales'         => Transaction::today()->successful()->sum('amount'),
            'todayCommissions'   => Transaction::today()->successful()->sum('commission'),
            'todayEarnings'      => Transaction::today()->successful()->sum('commission'),
            'weekTransactions'   => Transaction::thisWeek()->count(),
            'weekSales'          => Transaction::thisWeek()->successful()->sum('amount'),
            'pendingTopups'      => Topup::where('status', 'PENDING')->count(),
            'totalBalance'       => Retailer::sum('balance'),
            'totalEloadWallet'   => Retailer::sum('eload_balance'),
            'totalBillsWallet'   => Retailer::sum('bills_balance'),
            'machinesOnline'     => Device::where('last_seen_at', '>=', now()->subMinutes(5))->count(),
            'machinesTotal'      => Device::count(),
            'pendingAlerts'      => DeviceAlert::where('status', 'active')->count(),
            'monthTransactions'  => Transaction::thisMonth()->count(),
            'monthSales'         => Transaction::thisMonth()->successful()->sum('amount'),
            'totalTransactions'  => Transaction::count(),
            'totalSales'         => Transaction::successful()->sum('amount'),
            'totalProviders'     => Provider::count(),
            'activeProviders'    => Provider::where('is_active', true)->count(),
            'totalProducts'      => Product::count(),
            'failedToday'        => Transaction::today()->where('status', 'FAILED')->count(),
            'processingCount'    => Transaction::openStatuses()->count(),
            'pendingCount'       => Transaction::where('status', 'PENDING')->count(),
        ];

        $recentTransactions = Transaction::with('retailer')
            ->orderByRaw("CASE WHEN status IN ('PENDING', 'PROCESSING') THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $pendingTopups = Topup::with('retailer')
            ->where('status', 'PENDING')
            ->orderByDesc('created_at')
            ->get();

        return view('epayplus.dashboard', compact('stats', 'recentTransactions', 'pendingTopups'));
    }

    public function chartData(Request $request)
    {
        $range = $request->get('range', '7days');

        $days = match ($range) {
            '30days' => 30,
            '90days' => 90,
            default  => 7,
        };

        [$start, $end] = ManilaDateRange::lastDaysBounds($days);
        $tz = ManilaDateRange::timezone();

        $rows = Transaction::successful()
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at', 'amount', 'commission', 'type']);

        $salesByDate = $rows->groupBy(fn ($t) => $t->created_at->timezone($tz)->toDateString())
            ->map(fn ($group) => [
                'total_sales' => $group->sum('amount'),
                'total_commission' => $group->sum('commission'),
                'count' => $group->count(),
            ]);

        $labels = [];
        $sales = [];
        $commissions = [];
        $counts = [];

        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $day = $salesByDate->get($key, ['total_sales' => 0, 'total_commission' => 0, 'count' => 0]);

            $labels[] = $cursor->format('M d');
            $sales[] = (float) $day['total_sales'];
            $commissions[] = (float) $day['total_commission'];
            $counts[] = (int) $day['count'];
            $cursor->addDay();
        }

        $typeBreakdown = $rows->groupBy('type')
            ->map(fn ($group) => [
                'total' => $group->sum('amount'),
                'count' => $group->count(),
            ]);

        return response()->json([
            'labels'      => $labels,
            'sales'       => $sales,
            'commissions' => $commissions,
            'counts'      => $counts,
            'typeLabels'  => $typeBreakdown->keys()->values(),
            'typeTotals'  => $typeBreakdown->pluck('total')->values(),
            'typeCounts'  => $typeBreakdown->pluck('count')->values(),
        ]);
    }

    public function auditLog(Request $request)
    {
        $query = AuditLog::with('user')->orderByDesc('created_at');

        if ($request->action) {
            $query->where('action', $request->action);
        }
        if ($request->date) {
            $bounds = ManilaDateRange::fromStrings($request->date, $request->date);
            ManilaDateRange::applyBetween($query, 'created_at', $bounds);
        }
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $logs = $query->paginate(50);

        return view('epayplus.audit-log', compact('logs'));
    }
}
