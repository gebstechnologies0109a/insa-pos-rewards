<?php

use App\Models\EPayPlus\Retailer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy retailers: funds only in `balance`, or fully parked in `eload_balance` (pre-split migration).
        DB::table('epay_retailers')
            ->where('balance', '>', 0)
            ->where('bills_balance', '<=', 0)
            ->where(function ($query) {
                $query->where('eload_balance', '<=', 0)
                    ->orWhereRaw('ABS(eload_balance - balance) < 0.01');
            })
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $split = Retailer::splitBalanceFromTotal((float) $row->balance);
                    DB::table('epay_retailers')
                        ->where('id', $row->id)
                        ->update([
                            'eload_balance' => $split['eload'],
                            'bills_balance' => $split['bills'],
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Non-destructive: cannot safely reverse migrated balances.
    }
};
