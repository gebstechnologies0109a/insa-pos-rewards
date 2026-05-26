<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\POS\PosXReading;
use App\Models\POS\PosZReading;
use App\Services\ReadingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReadingController extends Controller
{
    public function __construct(private ReadingService $readingService)
    {
    }

    // ── POS API Endpoints ────────────────────────────────

    public function generateXReading(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $reading = $this->readingService->generateXReading($user);

        return response()->json([
            'success' => true,
            'reading' => [
                'id'                => $reading->id,
                'generated_at'      => $reading->generated_at->format('Y-m-d H:i:s'),
                'total_sales'       => $reading->total_sales,
                'transaction_count' => $reading->transaction_count,
                'void_total'        => $reading->void_total,
                'discount_total'    => $reading->discount_total,
                'payment_breakdown' => $reading->payment_breakdown,
            ],
        ]);
    }

    public function generateZReading(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $reading = $this->readingService->generateZReading($user);

        return response()->json([
            'success' => true,
            'reading' => [
                'id'                => $reading->id,
                'z_count'           => $reading->z_count,
                'generated_at'      => $reading->generated_at->format('Y-m-d H:i:s'),
                'total_sales'       => $reading->total_sales,
                'transaction_count' => $reading->transaction_count,
                'void_total'        => $reading->void_total,
                'discount_total'    => $reading->discount_total,
                'payment_breakdown' => $reading->payment_breakdown,
            ],
        ]);
    }

    // ── Back-Office Report Pages ─────────────────────────

    public function showXReading(Request $request)
    {
        $query = PosXReading::with(['branch', 'cashier'])->orderByDesc('generated_at');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }
        if ($request->filled('cashier_id')) {
            $query->where('cashier_id', $request->input('cashier_id'));
        }
        if ($request->filled('date')) {
            $query->whereDate('generated_at', $request->input('date'));
        }

        $readings = $query->paginate(50);
        $branches = Branch::orderBy('name')->get();

        return view('backoffice.readings.x', compact('readings', 'branches'));
    }

    public function showZReading(Request $request)
    {
        $query = PosZReading::with(['branch', 'cashier'])->orderByDesc('generated_at');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }
        if ($request->filled('cashier_id')) {
            $query->where('cashier_id', $request->input('cashier_id'));
        }
        if ($request->filled('date')) {
            $query->whereDate('generated_at', $request->input('date'));
        }
        if ($request->filled('z_count')) {
            $query->where('z_count', $request->input('z_count'));
        }

        $readings = $query->paginate(50);
        $branches = Branch::orderBy('name')->get();

        return view('backoffice.readings.z', compact('readings', 'branches'));
    }

    public function exportXReadingCsv(Request $request)
    {
        $query = PosXReading::with(['branch', 'cashier'])->orderByDesc('generated_at');

        if ($request->filled('branch_id')) $query->where('branch_id', $request->input('branch_id'));
        if ($request->filled('date')) $query->whereDate('generated_at', $request->input('date'));

        $readings = $query->get();
        $filename = 'x-readings-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($readings) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Branch', 'Cashier', 'Generated At', 'Total Sales', 'Transactions', 'Voids', 'Discounts', 'Cash', 'GCash', 'Maya', 'Card']);
            foreach ($readings as $r) {
                $pb = $r->payment_breakdown ?? [];
                fputcsv($out, [
                    $r->id,
                    $r->branch->name ?? '',
                    $r->cashier->name ?? '',
                    $r->generated_at->format('Y-m-d H:i:s'),
                    $r->total_sales,
                    $r->transaction_count,
                    $r->void_total,
                    $r->discount_total,
                    $pb['cash'] ?? 0,
                    $pb['gcash'] ?? 0,
                    $pb['maya'] ?? 0,
                    ($pb['debit_card'] ?? 0) + ($pb['credit_card'] ?? 0),
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportZReadingCsv(Request $request)
    {
        $query = PosZReading::with(['branch', 'cashier'])->orderByDesc('generated_at');

        if ($request->filled('branch_id')) $query->where('branch_id', $request->input('branch_id'));
        if ($request->filled('date')) $query->whereDate('generated_at', $request->input('date'));

        $readings = $query->get();
        $filename = 'z-readings-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($readings) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Z#', 'Branch', 'Cashier', 'Generated At', 'Total Sales', 'Transactions', 'Voids', 'Discounts', 'Cash', 'GCash', 'Maya', 'Card']);
            foreach ($readings as $r) {
                $pb = $r->payment_breakdown ?? [];
                fputcsv($out, [
                    $r->id,
                    $r->z_count,
                    $r->branch->name ?? '',
                    $r->cashier->name ?? '',
                    $r->generated_at->format('Y-m-d H:i:s'),
                    $r->total_sales,
                    $r->transaction_count,
                    $r->void_total,
                    $r->discount_total,
                    $pb['cash'] ?? 0,
                    $pb['gcash'] ?? 0,
                    $pb['maya'] ?? 0,
                    ($pb['debit_card'] ?? 0) + ($pb['credit_card'] ?? 0),
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}
