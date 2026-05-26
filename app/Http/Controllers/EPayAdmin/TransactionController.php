<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\AuditLog;
use App\Models\EPayPlus\Retailer;
use App\Models\EPayPlus\Transaction;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with('retailer')->orderByDesc('created_at');

        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->retailer_id) {
            $query->where('retailer_id', $request->retailer_id);
        }
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('reference_number', 'like', "%{$request->search}%")
                  ->orWhere('target_number', 'like', "%{$request->search}%")
                  ->orWhere('external_ref', 'like', "%{$request->search}%");
            });
        }

        $transactions = $query->paginate(50)->withQueryString();
        $retailers    = Retailer::orderBy('business_name')->get(['id', 'business_name']);

        return view('epayplus.transactions', compact('transactions', 'retailers'));
    }

    public function show(Transaction $transaction)
    {
        $transaction->load('retailer', 'product');
        return view('epayplus.transaction-detail', compact('transaction'));
    }

    public function updateStatus(Request $request, Transaction $transaction)
    {
        $request->validate([
            'status'  => 'required|in:SUCCESS,FAILED,PROCESSING,PENDING',
            'remarks' => 'nullable|string|max:500',
        ]);

        $oldStatus = $transaction->status;
        $transaction->update([
            'status'  => $request->status,
            'remarks' => $request->remarks,
            'completed_at' => in_array($request->status, ['SUCCESS', 'FAILED']) ? now() : null,
        ]);

        if ($request->status === 'FAILED' && $oldStatus !== 'FAILED') {
            $retailer = $transaction->retailer;
            if ($retailer && $transaction->retailer_cost > 0) {
                $retailer->addBalance($transaction->retailer_cost);
            }
        }

        AuditLog::record(auth()->id(), 'transaction_status_updated', $transaction, "Status: {$oldStatus} → {$request->status}");

        return back()->with('success', "Transaction status updated to {$request->status}.");
    }

    public function export(Request $request)
    {
        $query = Transaction::with('retailer')->orderByDesc('created_at');

        if ($request->type) $query->where('type', $request->type);
        if ($request->status) $query->where('status', $request->status);
        if ($request->date_from) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to) $query->whereDate('created_at', '<=', $request->date_to);
        if ($request->retailer_id) $query->where('retailer_id', $request->retailer_id);

        $transactions = $query->get();

        return new StreamedResponse(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Ref #', 'Retailer', 'Type', 'Product', 'Target', 'Amount', 'Fee', 'Commission', 'Cost', 'Status', 'Date']);

            foreach ($transactions as $t) {
                fputcsv($handle, [
                    $t->reference_number, $t->retailer?->business_name, $t->type, $t->product_name,
                    $t->target_number, $t->amount, $t->fee, $t->commission, $t->retailer_cost,
                    $t->status, $t->created_at->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="epayplus-transactions-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }
}
