<?php

namespace App\Services;

use App\Models\POS\PosSale;
use App\Models\POS\PosXReading;
use App\Models\POS\PosZReading;
use Illuminate\Support\Facades\DB;

class ReadingService
{
    /**
     * Build payment breakdown from a collection of sales.
     * Maps every payment_method used in the system.
     */
    private function paymentBreakdown($sales): array
    {
        return [
            'cash'        => (float) $sales->where('payment_method', 'cash')->sum('total'),
            'gcash'       => (float) $sales->where('payment_method', 'gcash')->sum('total'),
            'maya'        => (float) $sales->where('payment_method', 'maya')->sum('total'),
            'debit_card'  => (float) $sales->where('payment_method', 'debit_card')->sum('total'),
            'credit_card' => (float) $sales->where('payment_method', 'credit_card')->sum('total'),
            'palawanpay'  => (float) $sales->where('payment_method', 'palawanpay')->sum('total'),
            'other'       => (float) $sales->where('payment_method', 'other')->sum('total'),
        ];
    }

    /**
     * X-Reading: snapshot of current cashier's sales for today.
     * Does NOT reset totals. Can be generated anytime.
     */
    public function generateXReading($user): PosXReading
    {
        $completedSales = PosSale::where('cashier_id', $user->id)
            ->where('branch_id', $user->branch_id)
            ->whereDate('sold_at', today())
            ->where('status', 'completed')
            ->get();

        $voidedSales = PosSale::where('cashier_id', $user->id)
            ->where('branch_id', $user->branch_id)
            ->whereDate('sold_at', today())
            ->where('status', 'voided')
            ->get();

        return PosXReading::create([
            'branch_id'         => $user->branch_id,
            'terminal_id'       => $user->terminal_id ?? null,
            'cashier_id'        => $user->id,
            'generated_at'      => now(),
            'total_sales'       => $completedSales->sum('total'),
            'transaction_count' => $completedSales->count(),
            'void_total'        => $voidedSales->sum('total'),
            'discount_total'    => $completedSales->sum('discount_total'),
            'payment_breakdown' => $this->paymentBreakdown($completedSales),
        ]);
    }

    /**
     * Z-Reading: end-of-day reading for the entire branch.
     * RESETS totals by tagging sales with the Z-reading ID.
     * Z-count increments sequentially per branch (BIR requirement).
     */
    public function generateZReading($user): PosZReading
    {
        return DB::transaction(function () use ($user) {
            $lastZ = PosZReading::where('branch_id', $user->branch_id)->max('z_count') ?? 0;
            $zCount = $lastZ + 1;

            $completedSales = PosSale::where('branch_id', $user->branch_id)
                ->whereNull('z_reading_id')
                ->where('status', 'completed')
                ->get();

            $voidedSales = PosSale::where('branch_id', $user->branch_id)
                ->whereNull('z_reading_id')
                ->where('status', 'voided')
                ->get();

            $z = PosZReading::create([
                'branch_id'         => $user->branch_id,
                'terminal_id'       => $user->terminal_id ?? null,
                'cashier_id'        => $user->id,
                'z_count'           => $zCount,
                'generated_at'      => now(),
                'total_sales'       => $completedSales->sum('total'),
                'transaction_count' => $completedSales->count(),
                'void_total'        => $voidedSales->sum('total'),
                'discount_total'    => $completedSales->sum('discount_total'),
                'payment_breakdown' => $this->paymentBreakdown($completedSales),
            ]);

            // Mark all untagged sales as included in this Z-reading
            PosSale::where('branch_id', $user->branch_id)
                ->whereNull('z_reading_id')
                ->update(['z_reading_id' => $z->id]);

            return $z;
        });
    }
}
