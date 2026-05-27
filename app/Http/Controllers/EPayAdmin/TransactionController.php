<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\AuditLog;
use App\Models\EPayPlus\Retailer;
use App\Models\EPayPlus\Transaction;
use App\Support\ManilaDateRange;
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

        $filterMeta = $this->applyDateFilters($query, $request);

        $summary = [
            'total_amount' => (clone $query)->sum('amount'),
            'total_earnings' => (clone $query)->where('status', 'SUCCESS')->sum('commission'),
            'success_count' => (clone $query)->where('status', 'SUCCESS')->count(),
        ];

        $transactions = $query->paginate(50)->withQueryString();
        $retailers    = Retailer::orderBy('business_name')->get(['id', 'business_name', 'account_id']);

        return view('epayplus.transactions', compact('transactions', 'retailers', 'summary', 'filterMeta'));
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

        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->retailer_id) {
            $query->where('retailer_id', $request->retailer_id);
        }

        $this->applyDateFilters($query, $request);

        $transactions = $query->get();

        return new StreamedResponse(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Ref #', 'Retailer', 'Type', 'Product', 'Target', 'Amount', 'Fee', 'Commission', 'Cost', 'Status', 'Date']);

            foreach ($transactions as $t) {
                fputcsv($handle, [
                    $t->reference_number, $t->retailer?->business_name, $t->type, $t->product_name,
                    $t->target_number, $t->amount, $t->fee, $t->commission, $t->retailer_cost,
                    $t->status, $t->created_at->timezone(ManilaDateRange::timezone())->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="epayplus-transactions-' . ManilaDateRange::now()->format('Y-m-d') . '.csv"',
        ]);
    }

    private function applyDateFilters($query, Request $request): array
    {
        $showAll = $request->boolean('all');
        $hasFrom = $request->filled('date_from');
        $hasTo = $request->filled('date_to');

        if ($showAll) {
            return [
                'show_all' => true,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'label' => 'All time',
            ];
        }

        if ($hasFrom || $hasTo) {
            $bounds = ManilaDateRange::fromStrings($request->date_from, $request->date_to);
            ManilaDateRange::applyBetween($query, 'created_at', $bounds);

            return [
                'show_all' => false,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'label' => 'Custom range',
            ];
        }

        [$start, $end] = ManilaDateRange::lastDaysBounds(ManilaDateRange::DEFAULT_LIST_DAYS);
        ManilaDateRange::applyBetween($query, 'created_at', [$start, $end]);

        return [
            'show_all' => false,
            'date_from' => $start->toDateString(),
            'date_to' => $end->toDateString(),
            'label' => 'Last ' . ManilaDateRange::DEFAULT_LIST_DAYS . ' days',
        ];
    }
}
