<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\AuditLog;
use App\Models\EPayPlus\Retailer;
use App\Models\EPayPlus\Topup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TopupController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'pending');

        $pendingTopups = Topup::with('retailer')
            ->where('status', 'PENDING')
            ->orderByDesc('created_at')
            ->get();

        $query = Topup::with(['retailer', 'approver'])->orderByDesc('created_at');

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->retailer_id) {
            $query->where('retailer_id', $request->retailer_id);
        }
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $history   = $query->paginate(30)->withQueryString();
        $retailers = Retailer::where('is_active', true)->orderBy('business_name')->get(['id', 'business_name', 'balance']);

        return view('epayplus.topups', compact('pendingTopups', 'history', 'retailers', 'tab'));
    }

    public function approve(Request $request, Topup $topup)
    {
        if ($topup->status !== 'PENDING') {
            return back()->with('error', 'This top-up has already been processed.');
        }

        $topup->approve(auth()->id());

        AuditLog::record(auth()->id(), 'topup_approved', $topup, "₱{$topup->amount} for {$topup->retailer->business_name}");

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => "₱{$topup->amount} approved."]);
        }

        return back()->with('success', "₱{$topup->amount} top-up approved for {$topup->retailer->business_name}.");
    }

    public function reject(Request $request, Topup $topup)
    {
        if ($topup->status !== 'PENDING') {
            return back()->with('error', 'This top-up has already been processed.');
        }

        $request->validate(['remarks' => 'nullable|string|max:500']);
        $topup->reject(auth()->id(), $request->remarks);

        AuditLog::record(auth()->id(), 'topup_rejected', $topup, "₱{$topup->amount} for {$topup->retailer->business_name}");

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Top-up rejected.']);
        }

        return back()->with('error', 'Top-up rejected.');
    }

    public function manualCredit(Request $request)
    {
        $request->validate([
            'retailer_id' => 'required|exists:epay_retailers,id',
            'amount'      => 'required|numeric|min:1',
            'remarks'     => 'nullable|string|max:500',
        ]);

        $retailer = Retailer::findOrFail($request->retailer_id);

        Topup::create([
            'retailer_id'      => $retailer->id,
            'amount'           => $request->amount,
            'payment_method'   => 'MANUAL_CREDIT',
            'reference_number' => 'MC-' . now()->format('ymdHis') . '-' . Str::random(4),
            'status'           => 'APPROVED',
            'remarks'          => $request->remarks ?? 'Manual credit by admin',
            'balance_before'   => $retailer->balance,
            'balance_after'    => $retailer->balance + $request->amount,
            'approved_by'      => auth()->id(),
            'approved_at'      => now(),
        ]);

        $retailer->addBalance($request->amount);

        AuditLog::record(auth()->id(), 'manual_credit', $retailer, "₱{$request->amount} credited to {$retailer->business_name}");

        return back()->with('success', "₱" . number_format($request->amount, 2) . " credited to {$retailer->business_name}.");
    }
}
