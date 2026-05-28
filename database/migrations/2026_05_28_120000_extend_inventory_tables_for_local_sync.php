<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'inventory_batches',
            'stock_movements',
            'pos_sales',
            'pos_sale_items',
            'expiry_alerts',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'synced_at')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->timestamp('synced_at')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['inventory_batches', 'stock_movements', 'pos_sales', 'pos_sale_items', 'expiry_alerts'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'synced_at')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('synced_at');
                });
            }
        }
    }
};
