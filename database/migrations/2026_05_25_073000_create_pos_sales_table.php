<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->unique();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('cashier_id');
            $table->unsignedBigInteger('member_id')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('payment_method')->default('cash');
            $table->decimal('amount_tendered', 12, 2)->default(0);
            $table->decimal('change_due', 12, 2)->default(0);
            $table->string('status')->default('completed');
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'sold_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sales');
    }
};
