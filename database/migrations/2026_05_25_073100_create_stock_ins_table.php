<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('stock_ins')) {
            return;
        }

        Schema::create('stock_ins', function (Blueprint $table) {
            $table->id();
            $table->string('stock_in_number')->unique();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('user_id');
            $table->string('supplier_name')->nullable();
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ins');
    }
};
