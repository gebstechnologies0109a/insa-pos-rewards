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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'totalRetailers'    => Retailer::count(),
            'activeRetailers'   => Retailer::where('is_active', true)->count(),
            'todayTransactions' => Transaction::today()->count(),
            'todaySales'        => Transaction::today()->successful()->sum('amount'),
            'todayCommissions'  => Transaction::today()->successful()->sum('commission'),
            'todayEarnings'     => Transaction::today()->successful()->sum('commission'),
            'pendingTopups'     => Topup::where('status', 'PENDING')->count(),
            'totalBalance'      => Retailer::sum('balance'),
            'totalEloadWallet'  => Retailer::sum('eload_balance'),
            'totalBillsWallet'  => Retailer::sum('bills_balance'),
            'machinesOnline'    => Device::where('last_seen_at', '>=', now()->subMinutes(5))->count(),
            'machinesTotal'     => Device::count(),
            'pendingAlerts'     => DeviceAlert::where('status', 'active')->count(),
            'monthTransactions' => Transaction::whereMonth('created_at', now()->month)->count(),
            'monthSales'        => Transaction::whereMonth('created_at', now()->month)->successful()->sum('amount'),
            'totalProviders'    => Provider::count(),
            'activeProviders'   => Provider::where('is_active', true)->count(),
            'totalProducts'     => Product::count(),
            'failedToday'       => Transaction::today()->where('status', 'FAILED')->count(),
            'processingCount'   => Transaction::where('status', 'PROCESSING')->count(),
        ];

        $recentTransactions = Transaction::with('retailer')
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

        $salesData = Transaction::successful()
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total_sales, SUM(commission) as total_commission, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $typeBreakdown = Transaction::successful()
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('type, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('type')
            ->get();

        return response()->json([
            'labels'      => $salesData->pluck('date')->map(fn ($d) => \Carbon\Carbon::parse($d)->format('M d')),
            'sales'       => $salesData->pluck('total_sales'),
            'commissions' => $salesData->pluck('total_commission'),
            'counts'      => $salesData->pluck('count'),
            'typeLabels'  => $typeBreakdown->pluck('type'),
            'typeTotals'  => $typeBreakdown->pluck('total'),
            'typeCounts'  => $typeBreakdown->pluck('count'),
        ]);
    }

    public function auditLog(Request $request)
    {
        $query = AuditLog::with('user')->orderByDesc('created_at');

        if ($request->action) {
            $query->where('action', $request->action);
        }
        if ($request->date) {
            $query->whereDate('created_at', $request->date);
        }
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $logs = $query->paginate(50);

        return view('epayplus.audit-log', compact('logs'));
    }
}
