<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\POS\PosSale;
use App\Models\POS\PosXReading;
use App\Models\POS\PosZReading;
use App\Services\ReadingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
        $this->applyReadingDateFilter($query, $request->input('date'));

        $readings = $query->paginate(50);
        $branches = Branch::orderBy('name')->get();

        return view('backoffice.readings.x', compact('readings', 'branches'));
    }

    public function viewXReading(PosXReading $xReading)
    {
        $reading = $xReading->load(['branch', 'cashier']);

        $salesQuery = PosSale::query()
            ->where('cashier_id', $reading->cashier_id)
            ->where('branch_id', $reading->branch_id)
            ->whereDate('sold_at', $reading->generated_at)
            ->where('sold_at', '<=', $reading->generated_at);

        $completedSales = (clone $salesQuery)
            ->where('status', 'completed')
            ->orderBy('sold_at')
            ->get();

        $voidedSales = (clone $salesQuery)
            ->where('status', 'voided')
            ->orderBy('sold_at')
            ->get();

        return view('backoffice.readings.x-show', compact('reading', 'completedSales', 'voidedSales'));
    }

    public function showZReading(Request $request)
    {
        $query = PosZReading::with(['branch', 'cashier'])->orderByDesc('generated_at');
        $this->applyReadingDateFilter($query, $request->input('date'));

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }
        if ($request->filled('cashier_id')) {
            $query->where('cashier_id', $request->input('cashier_id'));
        }
        if ($request->filled('z_count')) {
            $query->where('z_count', $request->input('z_count'));
        }

        $readings = $query->paginate(50);
        $branches = Branch::orderBy('name')->get();

        $pendingUntaggedCount = 0;
        if ($request->filled('branch_id')) {
            $pendingUntaggedCount = $this->readingService->countUntaggedSales(
                (int) $request->input('branch_id'),
                $request->filled('date') ? $request->input('date') : null,
            );
        }

        return view('backoffice.readings.z', compact('readings', 'branches', 'pendingUntaggedCount'));
    }

    public function storeZReading(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'date'      => 'nullable|date_format:Y-m-d',
        ]);

        $branchId = (int) $validated['branch_id'];
        $salesDate = $validated['date'] ?? null;

        if ($this->readingService->countUntaggedSales($branchId, $salesDate) === 0) {
            return redirect()
                ->route('readings.z', $request->only(['branch_id', 'date', 'z_count', 'cashier_id']))
                ->with('error', 'No untagged sales to include in a Z-reading for this branch' . ($salesDate ? " on {$salesDate}" : '') . '.');
        }

        $generatedAt = $salesDate
            ? Carbon::parse($salesDate, config('app.timezone'))->endOfDay()
            : null;

        $reading = $this->readingService->generateZReadingForBranch(
            $branchId,
            $request->user()->id,
            null,
            $generatedAt,
            $salesDate,
        );

        return redirect()
            ->route('readings.z', [
                'branch_id' => $branchId,
                'date'      => $reading->generated_at->timezone(config('app.timezone'))->toDateString(),
            ])
            ->with('success', "Z-Reading #{$reading->z_count} generated successfully.");
    }

    public function exportXReadingCsv(Request $request)
    {
        $query = PosXReading::with(['branch', 'cashier'])->orderByDesc('generated_at');

        if ($request->filled('branch_id')) $query->where('branch_id', $request->input('branch_id'));
        $this->applyReadingDateFilter($query, $request->input('date'));

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
        $this->applyReadingDateFilter($query, $request->input('date'));

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

    /**
     * Filter readings by calendar date in the application timezone (Asia/Manila).
     */
    private function applyReadingDateFilter($query, ?string $date): void
    {
        if (! $date) {
            return;
        }

        $day = Carbon::parse($date, config('app.timezone'));

        $query->whereBetween('generated_at', [
            $day->copy()->startOfDay(),
            $day->copy()->endOfDay(),
        ]);
    }
}
