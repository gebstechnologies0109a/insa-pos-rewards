<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\POS\PosShift;
use App\Models\User;
use Illuminate\Http\Request;

class ShiftManagementController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = PosShift::with(['user', 'branch']);

        if ($user->isManager() && $user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('opened_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('opened_at', '<=', $request->date_to);
        }

        if ($request->input('export') === 'csv') {
            return $this->exportCsv(clone $query);
        }

        if ($request->input('export') === 'pdf') {
            return $this->exportPdf(clone $query);
        }

        $shifts = $query->orderByDesc('opened_at')->paginate(50)->withQueryString();

        $branches = Branch::orderBy('name')->get();
        $cashiers = User::whereIn('role', ['cashier', 'manager', 'admin', 'owner'])
            ->orderBy('name')
            ->get();

        return view('backoffice.shifts.index', compact('shifts', 'branches', 'cashiers'));
    }

    protected function exportCsv($query)
    {
        $shifts = $query->orderByDesc('opened_at')->get();

        return response()->streamDownload(function () use ($shifts) {
            $f = fopen('php://output', 'w');
            fputcsv($f, ['Shift ID', 'Cashier', 'Branch', 'Opened At', 'Closed At', 'Opening Cash', 'Closing Cash', 'System Sales', 'Variance', 'Status']);
            foreach ($shifts as $s) {
                fputcsv($f, [
                    $s->id,
                    $s->user?->name,
                    $s->branch?->name,
                    $s->opened_at?->format('Y-m-d H:i'),
                    $s->closed_at?->format('Y-m-d H:i'),
                    number_format($s->opening_cash, 2),
                    $s->closing_cash !== null ? number_format($s->closing_cash, 2) : '',
                    $s->system_sales_total !== null ? number_format($s->system_sales_total, 2) : '',
                    $s->cash_variance !== null ? number_format($s->cash_variance, 2) : '',
                    $s->status,
                ]);
            }
            fclose($f);
        }, 'shifts-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    protected function exportPdf($query)
    {
        $shifts = $query->orderByDesc('opened_at')->get();
        $html = view('backoffice.shifts.pdf-list', compact('shifts'))->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="shifts-' . now()->format('Y-m-d') . '.pdf"',
        ]);
    }
}
