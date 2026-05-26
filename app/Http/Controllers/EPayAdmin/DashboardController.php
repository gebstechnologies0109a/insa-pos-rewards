<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Retailer;
use App\Models\EPayPlus\Transaction;
use App\Models\EPayPlus\Topup;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'totalRetailers' => Retailer::count(),
            'activeRetailers' => Retailer::where('is_active', true)->count(),
            'todayTransactions' => Transaction::today()->count(),
            'todaySales' => Transaction::today()->successful()->sum('amount'),
            'todayCommissions' => Transaction::today()->successful()->sum('commission'),
            'pendingTopups' => Topup::where('status', 'PENDING')->count(),
            'totalBalance' => Retailer::sum('balance'),
            'monthTransactions' => Transaction::whereMonth('created_at', now()->month)->count(),
            'monthSales' => Transaction::whereMonth('created_at', now()->month)->successful()->sum('amount'),
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

    public function retailers()
    {
        $retailers = Retailer::withCount('transactions')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('epayplus.retailers', compact('retailers'));
    }

    public function retailerDetail(Retailer $retailer)
    {
        $retailer->loadCount('transactions');
        $transactions = $retailer->transactions()->orderByDesc('created_at')->limit(50)->get();
        $topups = $retailer->topups()->orderByDesc('created_at')->limit(20)->get();

        return view('epayplus.retailer-detail', compact('retailer', 'transactions', 'topups'));
    }

    public function approveTopup(Topup $topup, Request $request)
    {
        if ($topup->status !== 'PENDING') {
            return back()->with('error', 'This top-up has already been processed.');
        }

        $topup->approve(auth()->id());

        return back()->with('success', "₱{$topup->amount} top-up approved for {$topup->retailer->business_name}.");
    }

    public function rejectTopup(Topup $topup, Request $request)
    {
        $request->validate(['remarks' => 'nullable|string']);

        $topup->reject(auth()->id(), $request->remarks);

        return back()->with('error', 'Top-up rejected.');
    }

    public function addBalance(Request $request)
    {
        $request->validate([
            'retailer_id' => 'required|exists:epay_retailers,id',
            'amount' => 'required|numeric|min:1',
            'remarks' => 'nullable|string',
        ]);

        $retailer = Retailer::findOrFail($request->retailer_id);

        $topup = Topup::create([
            'retailer_id' => $retailer->id,
            'amount' => $request->amount,
            'payment_method' => 'ADMIN',
            'reference_number' => 'ADMIN-' . now()->format('ymdHis'),
            'status' => 'APPROVED',
            'remarks' => $request->remarks ?? 'Manual top-up by admin',
            'balance_before' => $retailer->balance,
            'balance_after' => $retailer->balance + $request->amount,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $retailer->addBalance($request->amount);

        return back()->with('success', "₱{$request->amount} added to {$retailer->business_name}.");
    }

    public function transactions(Request $request)
    {
        $query = Transaction::with('retailer')->orderByDesc('created_at');

        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->date) {
            $query->whereDate('created_at', $request->date);
        }

        $transactions = $query->paginate(50);

        return view('epayplus.transactions', compact('transactions'));
    }
}
