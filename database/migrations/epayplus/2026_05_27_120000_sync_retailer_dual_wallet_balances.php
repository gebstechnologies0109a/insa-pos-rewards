<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy retailers: funds only in `balance`, dual-wallet columns still zero.
        DB::table('epay_retailers')
            ->where('balance', '>', 0)
            ->where('eload_balance', '<=', 0)
            ->where('bills_balance', '<=', 0)
            ->update([
                'eload_balance' => DB::raw('balance'),
            ]);
    }

    public function down(): void
    {
        // Non-destructive: cannot safely reverse migrated balances.
    }
};
