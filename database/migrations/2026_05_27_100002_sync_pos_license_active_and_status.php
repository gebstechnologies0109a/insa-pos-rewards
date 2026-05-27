<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_licenses')) {
            return;
        }

        if (! Schema::hasColumn('pos_licenses', 'status')) {
            return;
        }

        DB::table('pos_licenses')
            ->where('active', true)
            ->update(['status' => 'active']);

        DB::table('pos_licenses')
            ->where('active', false)
            ->where('status', 'active')
            ->update(['status' => 'suspended']);

        DB::table('pos_licenses')
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '');
            })
            ->where('pos_slots', '>', 0)
            ->update(['status' => 'active']);
    }

    public function down(): void
    {
        // Data repair only; no rollback.
    }
};
