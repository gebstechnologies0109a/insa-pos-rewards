<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_z_readings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->unsignedBigInteger('cashier_id');
            $table->integer('z_count');
            $table->timestamp('generated_at');
            $table->decimal('total_sales', 12, 2)->default(0);
            $table->integer('transaction_count')->default(0);
            $table->decimal('void_total', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->json('payment_breakdown')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'z_count']);
            $table->index(['branch_id', 'cashier_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_z_readings');
    }
};
