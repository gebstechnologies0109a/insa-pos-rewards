<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Provider;
use App\Models\EPayPlus\Retailer;
use App\Models\EPayPlus\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index()
    {
        return view('epayplus.reports');
    }

    public function dailySales(Request $request)
    {
        $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
        $dateTo   = $request->get('date_to', now()->toDateString());

        $sales = Transaction::successful()
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->selectRaw('DATE(created_at) as date, type, COUNT(*) as count, SUM(amount) as total_amount, SUM(commission) as total_commission, SUM(fee) as total_fee')
            ->groupBy('date', 'type')
            ->orderBy('date')
            ->get();

        $dailyTotals = $sales->groupBy('date')->map(function ($dayGroup) {
            return [
                'count'      => $dayGroup->sum('count'),
                'amount'     => $dayGroup->sum('total_amount'),
                'commission' => $dayGroup->sum('total_commission'),
                'fee'        => $dayGroup->sum('total_fee'),
                'types'      => $dayGroup->keyBy('type'),
            ];
        });

        return view('epayplus.reports-daily', compact('dailyTotals', 'dateFrom', 'dateTo'));
    }

    public function commissions(Request $request)
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo   = $request->get('date_to', now()->toDateString());

        $commissions = Transaction::successful()
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->selectRaw('type, provider_code, COUNT(*) as count, SUM(amount) as total_amount, SUM(commission) as total_commission')
            ->groupBy('type', 'provider_code')
            ->orderByDesc('total_commission')
            ->get();

        $totals = [
            'count'      => $commissions->sum('count'),
            'amount'     => $commissions->sum('total_amount'),
            'commission' => $commissions->sum('total_commission'),
        ];

        return view('epayplus.reports-commissions', compact('commissions', 'totals', 'dateFrom', 'dateTo'));
    }

    public function retailerPerformance(Request $request)
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo   = $request->get('date_to', now()->toDateString());

        $retailers = Retailer::withCount(['transactions as period_txn_count' => function ($q) use ($dateFrom, $dateTo) {
            $q->where('status', 'SUCCESS')
              ->whereDate('created_at', '>=', $dateFrom)
              ->whereDate('created_at', '<=', $dateTo);
        }])
        ->withSum(['transactions as period_sales' => function ($q) use ($dateFrom, $dateTo) {
            $q->where('status', 'SUCCESS')
              ->whereDate('created_at', '>=', $dateFrom)
              ->whereDate('created_at', '<=', $dateTo);
        }], 'amount')
        ->withSum(['transactions as period_commission' => function ($q) use ($dateFrom, $dateTo) {
            $q->where('status', 'SUCCESS')
              ->whereDate('created_at', '>=', $dateFrom)
              ->whereDate('created_at', '<=', $dateTo);
        }], 'commission')
        ->orderByDesc('period_sales')
        ->paginate(25)
        ->withQueryString();

        return view('epayplus.reports-retailers', compact('retailers', 'dateFrom', 'dateTo'));
    }

    public function providerPerformance(Request $request)
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo   = $request->get('date_to', now()->toDateString());

        $providers = Transaction::successful()
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->selectRaw('provider_code, type, COUNT(*) as count, SUM(amount) as total_amount, SUM(commission) as total_commission, SUM(fee) as total_fee')
            ->groupBy('provider_code', 'type')
            ->orderByDesc('total_amount')
            ->get();

        return view('epayplus.reports-providers', compact('providers', 'dateFrom', 'dateTo'));
    }

    public function export(Request $request, string $type)
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo   = $request->get('date_to', now()->toDateString());

        return match ($type) {
            'daily-sales'           => $this->exportDailySales($dateFrom, $dateTo),
            'commissions'           => $this->exportCommissions($dateFrom, $dateTo),
            'retailer-performance'  => $this->exportRetailerPerformance($dateFrom, $dateTo),
            'provider-performance'  => $this->exportProviderPerformance($dateFrom, $dateTo),
            default                 => back()->with('error', 'Unknown report type.'),
        };
    }

    private function exportDailySales($from, $to): StreamedResponse
    {
        $data = Transaction::successful()
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('DATE(created_at) as date, type, COUNT(*) as count, SUM(amount) as amount, SUM(commission) as commission')
            ->groupBy('date', 'type')
            ->orderBy('date')
            ->get();

        return $this->streamCsv("daily-sales-{$from}-{$to}.csv",
            ['Date', 'Type', 'Transactions', 'Amount', 'Commission'],
            $data->map(fn ($r) => [$r->date, $r->type, $r->count, $r->amount, $r->commission])->toArray()
        );
    }

    private function exportCommissions($from, $to): StreamedResponse
    {
        $data = Transaction::successful()
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('type, provider_code, COUNT(*) as count, SUM(amount) as amount, SUM(commission) as commission')
            ->groupBy('type', 'provider_code')
            ->orderByDesc('commission')
            ->get();

        return $this->streamCsv("commissions-{$from}-{$to}.csv",
            ['Type', 'Provider', 'Transactions', 'Amount', 'Commission'],
            $data->map(fn ($r) => [$r->type, $r->provider_code, $r->count, $r->amount, $r->commission])->toArray()
        );
    }

    private function exportRetailerPerformance($from, $to): StreamedResponse
    {
        $retailers = Retailer::withCount(['transactions as txn_count' => fn ($q) => $q->where('status', 'SUCCESS')->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)])
            ->withSum(['transactions as sales' => fn ($q) => $q->where('status', 'SUCCESS')->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)], 'amount')
            ->withSum(['transactions as commission' => fn ($q) => $q->where('status', 'SUCCESS')->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)], 'commission')
            ->orderByDesc('sales')
            ->get();

        return $this->streamCsv("retailer-performance-{$from}-{$to}.csv",
            ['Retailer', 'Owner', 'Mobile', 'Balance', 'Transactions', 'Sales', 'Commission'],
            $retailers->map(fn ($r) => [$r->business_name, $r->owner_name, $r->mobile_number, $r->balance, $r->txn_count, $r->sales, $r->commission])->toArray()
        );
    }

    private function exportProviderPerformance($from, $to): StreamedResponse
    {
        $data = Transaction::successful()
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('provider_code, type, COUNT(*) as count, SUM(amount) as amount, SUM(commission) as commission')
            ->groupBy('provider_code', 'type')
            ->orderByDesc('amount')
            ->get();

        return $this->streamCsv("provider-performance-{$from}-{$to}.csv",
            ['Provider', 'Type', 'Transactions', 'Amount', 'Commission'],
            $data->map(fn ($r) => [$r->provider_code, $r->type, $r->count, $r->amount, $r->commission])->toArray()
        );
    }

    private function streamCsv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return new StreamedResponse(function () use ($headers, $rows) {
            $h = fopen('php://output', 'w');
            fputcsv($h, $headers);
            foreach ($rows as $row) {
                fputcsv($h, $row);
            }
            fclose($h);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
