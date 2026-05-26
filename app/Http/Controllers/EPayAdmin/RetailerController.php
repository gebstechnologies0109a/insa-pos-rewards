<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\AuditLog;
use App\Models\EPayPlus\Retailer;
use App\Models\EPayPlus\Topup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RetailerController extends Controller
{
    public function index(Request $request)
    {
        $query = Retailer::withCount('transactions');

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                  ->orWhere('owner_name', 'like', "%{$search}%")
                  ->orWhere('mobile_number', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('account_id', 'like', "%{$search}%");
            });
        }

        if ($request->status === 'active') {
            $query->where('is_active', true);
        } elseif ($request->status === 'inactive') {
            $query->where('is_active', false);
        }

        $sort = $request->get('sort', 'created_at');
        $dir  = $request->get('dir', 'desc');
        $query->orderBy($sort, $dir);

        $retailers = $query->paginate(25)->withQueryString();

        return view('epayplus.retailers', compact('retailers'));
    }

    public function create()
    {
        return view('epayplus.retailer-form', ['retailer' => null]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'business_name'  => 'required|string|max:255',
            'owner_name'     => 'required|string|max:255',
            'mobile_number'  => 'required|string|max:20|unique:epay_retailers,mobile_number',
            'email'          => 'nullable|email|max:255',
            'address'        => 'nullable|string|max:500',
            'credit_limit'   => 'nullable|numeric|min:0',
        ]);

        $data['account_id'] = 'EP-' . strtoupper(Str::random(8));
        $data['pin']        = bcrypt('1234');
        $data['api_token']  = Str::random(64);
        $data['balance']    = 0;
        $data['is_active']  = true;

        $retailer = Retailer::create($data);

        AuditLog::record(auth()->id(), 'retailer_created', $retailer, "Created retailer: {$retailer->business_name}");

        return redirect()->route('epayplus.retailers.show', $retailer)
            ->with('success', "Retailer '{$retailer->business_name}' created. Default PIN: 1234");
    }

    public function show(Retailer $retailer)
    {
        $retailer->loadCount('transactions');
        $transactions = $retailer->transactions()->orderByDesc('created_at')->limit(50)->get();
        $topups       = $retailer->topups()->orderByDesc('created_at')->limit(20)->get();

        return view('epayplus.retailer-detail', compact('retailer', 'transactions', 'topups'));
    }

    public function edit(Retailer $retailer)
    {
        return view('epayplus.retailer-form', compact('retailer'));
    }

    public function update(Request $request, Retailer $retailer)
    {
        $data = $request->validate([
            'business_name'  => 'required|string|max:255',
            'owner_name'     => 'required|string|max:255',
            'mobile_number'  => 'required|string|max:20|unique:epay_retailers,mobile_number,' . $retailer->id,
            'email'          => 'nullable|email|max:255',
            'address'        => 'nullable|string|max:500',
            'credit_limit'   => 'nullable|numeric|min:0',
        ]);

        $retailer->update($data);

        AuditLog::record(auth()->id(), 'retailer_updated', $retailer, "Updated retailer: {$retailer->business_name}");

        return redirect()->route('epayplus.retailers.show', $retailer)
            ->with('success', 'Retailer updated successfully.');
    }

    public function toggleStatus(Retailer $retailer)
    {
        $retailer->update(['is_active' => !$retailer->is_active]);
        $status = $retailer->is_active ? 'activated' : 'deactivated';

        AuditLog::record(auth()->id(), "retailer_{$status}", $retailer, "{$retailer->business_name} {$status}");

        return back()->with('success', "Retailer {$status} successfully.");
    }

    public function adjustBalance(Request $request, Retailer $retailer)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'type'   => 'required|in:credit,debit',
            'reason' => 'required|string|max:500',
        ]);

        $amount = (float) $request->amount;
        $balanceBefore = $retailer->balance;

        if ($request->type === 'debit') {
            if ($retailer->balance < $amount) {
                return back()->with('error', 'Insufficient balance for debit.');
            }
            $retailer->decrement('balance', $amount);
            $balanceAfter = $balanceBefore - $amount;
        } else {
            $retailer->increment('balance', $amount);
            $balanceAfter = $balanceBefore + $amount;
        }

        Topup::create([
            'retailer_id'      => $retailer->id,
            'amount'           => $request->type === 'debit' ? -$amount : $amount,
            'payment_method'   => 'ADMIN_ADJUSTMENT',
            'reference_number' => 'ADJ-' . now()->format('ymdHis') . '-' . Str::random(4),
            'status'           => 'APPROVED',
            'remarks'          => "[{$request->type}] {$request->reason}",
            'balance_before'   => $balanceBefore,
            'balance_after'    => $balanceAfter,
            'approved_by'      => auth()->id(),
            'approved_at'      => now(),
        ]);

        AuditLog::record(auth()->id(), 'balance_adjusted', $retailer, "{$request->type} ₱{$amount} - {$request->reason}");

        $label = $request->type === 'credit' ? 'credited to' : 'debited from';
        return back()->with('success', "₱" . number_format($amount, 2) . " {$label} {$retailer->business_name}.");
    }

    public function resetPin(Retailer $retailer)
    {
        $newPin = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $retailer->update(['pin' => bcrypt($newPin)]);

        AuditLog::record(auth()->id(), 'pin_reset', $retailer, "PIN reset for {$retailer->business_name}");

        return back()->with('success', "PIN reset for {$retailer->business_name}. New PIN: {$newPin}");
    }
}
