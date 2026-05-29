<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('inventory_batches')) {
            return;
        }

        Schema::table('inventory_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_batches', 'supplier_name')) {
                $table->string('supplier_name')->nullable();
            }

            if (! Schema::hasColumn('inventory_batches', 'received_at')) {
                $table->timestamp('received_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_batches')) {
            return;
        }

        Schema::table('inventory_batches', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_batches', 'supplier_name')) {
                $table->dropColumn('supplier_name');
            }

            if (Schema::hasColumn('inventory_batches', 'received_at')) {
                $table->dropColumn('received_at');
            }
        });
    }
};
