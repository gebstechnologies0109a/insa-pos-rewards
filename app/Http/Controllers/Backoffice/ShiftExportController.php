<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\POS\PosShift;
use Barryvdh\DomPDF\Facade\Pdf;

class ShiftExportController extends Controller
{
    public function show(PosShift $shift)
    {
        $shift->load(['user', 'branch', 'sales', 'audits.user']);

        return view('backoffice.shifts.show', compact('shift'));
    }

    public function exportCsv(PosShift $shift)
    {
        $shift->load(['user', 'branch', 'sales']);

        return response()->streamDownload(function () use ($shift) {
            $f = fopen('php://output', 'w');

            fputcsv($f, ['Shift Report']);
            fputcsv($f, ['Shift ID', $shift->id]);
            fputcsv($f, ['Cashier', $shift->user?->name]);
            fputcsv($f, ['Branch', $shift->branch?->name]);
            fputcsv($f, ['Opened At', $shift->opened_at?->format('Y-m-d H:i')]);
            fputcsv($f, ['Closed At', $shift->closed_at?->format('Y-m-d H:i')]);
            fputcsv($f, ['Opening Cash', number_format($shift->opening_cash, 2)]);
            fputcsv($f, ['Closing Cash', $shift->closing_cash !== null ? number_format($shift->closing_cash, 2) : '']);
            fputcsv($f, ['System Sales', $shift->system_sales_total !== null ? number_format($shift->system_sales_total, 2) : '']);
            fputcsv($f, ['Expected Cash', $shift->closing_cash !== null ? number_format($shift->opening_cash + $shift->system_sales_total, 2) : '']);
            fputcsv($f, ['Variance', $shift->cash_variance !== null ? number_format($shift->cash_variance, 2) : '']);
            fputcsv($f, ['Status', $shift->status]);
            fputcsv($f, []);

            fputcsv($f, ['Sales During This Shift']);
            fputcsv($f, ['Sale #', 'Total', 'Payment', 'Time']);

            foreach ($shift->sales as $sale) {
                fputcsv($f, [
                    $sale->sale_number,
                    number_format($sale->total, 2),
                    $sale->payment_method,
                    $sale->sold_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($f);
        }, "shift-{$shift->id}.csv", ['Content-Type' => 'text/csv']);
    }

    public function exportPdf(PosShift $shift)
    {
        $shift->load(['user', 'branch', 'sales']);

        $pdf = Pdf::loadView('backoffice.shifts.pdf', compact('shift'));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("shift-{$shift->id}.pdf");
    }
}
