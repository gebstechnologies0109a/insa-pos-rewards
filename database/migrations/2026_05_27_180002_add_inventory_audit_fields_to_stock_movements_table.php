<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_movements', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('product_id');
            }
            if (!Schema::hasColumn('stock_movements', 'inventory_batch_id')) {
                $table->unsignedBigInteger('inventory_batch_id')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('stock_movements', 'reason')) {
                $table->string('reason')->nullable()->after('reference_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'inventory_batch_id', 'reason']);
        });
    }
};
