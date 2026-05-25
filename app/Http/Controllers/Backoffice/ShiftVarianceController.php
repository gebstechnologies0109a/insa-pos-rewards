<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\POS\PosShift;
use App\Models\User;
use Illuminate\Http\Request;

class ShiftVarianceController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $branches = Branch::orderBy('name')->get();
        $cashiers = User::whereIn('role', ['cashier', 'manager', 'admin', 'owner'])
            ->orderBy('name')->get();

        $query = PosShift::with(['user', 'branch'])->where('status', 'closed');

        if ($user->isManager() && $user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('closed_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('closed_at', '<=', $request->date_to);
        }

        if ($request->filled('variance_type')) {
            if ($request->variance_type === 'over') {
                $query->where('cash_variance', '>', 0);
            } elseif ($request->variance_type === 'short') {
                $query->where('cash_variance', '<', 0);
            } elseif ($request->variance_type === 'exact') {
                $query->where('cash_variance', 0);
            }
        }

        $shifts = $query->orderByDesc('closed_at')->paginate(50)->withQueryString();

        $summary = (object) [
            'total_shifts'      => (clone $query)->count(),
            'total_variance'    => (clone $query)->sum('cash_variance'),
            'over_count'        => (clone $query)->where('cash_variance', '>', 0)->count(),
            'short_count'       => (clone $query)->where('cash_variance', '<', 0)->count(),
            'exact_count'       => (clone $query)->where('cash_variance', 0)->count(),
        ];

        $branchId = $request->input('branch_id', $user->branch_id);

        if ($request->input('export') === 'csv') {
            $allShifts = (clone $query)->orderByDesc('closed_at')->get();
            return $this->exportCsv($allShifts);
        }

        return view('backoffice.shifts.variance', compact(
            'shifts', 'branches', 'cashiers', 'summary', 'branchId',
        ));
    }

    protected function exportCsv($shifts)
    {
        $filename = 'shift-variance-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($shifts) {
            $f = fopen('php://output', 'w');
            fputcsv($f, ['Cashier', 'Branch', 'Closed At', 'Opening Cash', 'System Sales', 'Expected Cash', 'Closing Cash', 'Variance']);

            foreach ($shifts as $s) {
                $expected = $s->opening_cash + $s->system_sales_total;
                fputcsv($f, [
                    $s->user?->name,
                    $s->branch?->name,
                    $s->closed_at?->format('Y-m-d H:i'),
                    number_format($s->opening_cash, 2),
                    number_format($s->system_sales_total, 2),
                    number_format($expected, 2),
                    number_format($s->closing_cash, 2),
                    number_format($s->cash_variance, 2),
                ]);
            }

            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }
}
