<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('product_id');
            $table->string('batch_code')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->string('supplier_name')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'product_id']);
            $table->index(['branch_id', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_batches');
    }
};
