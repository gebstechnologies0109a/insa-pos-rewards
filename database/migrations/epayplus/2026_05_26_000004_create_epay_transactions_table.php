<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epay_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retailer_id')->constrained('epay_retailers')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('epay_products')->nullOnDelete();
            $table->string('type'); // ELOAD, BILLS, ECASH, WIFI, TOPUP
            $table->string('reference_number')->unique();
            $table->string('provider_code');
            $table->string('product_code')->nullable();
            $table->string('product_name')->nullable();
            $table->string('target_number');
            $table->decimal('amount', 10, 2);
            $table->decimal('fee', 10, 2)->default(0);
            $table->decimal('commission', 10, 2)->default(0);
            $table->decimal('retailer_cost', 10, 2)->default(0);
            $table->string('status')->default('PENDING'); // PENDING, PROCESSING, SUCCESS, FAILED, REFUNDED
            $table->string('payment_method')->default('WALLET'); // WALLET, CASH, COINS
            $table->string('remarks')->nullable();
            $table->string('external_ref')->nullable();
            $table->decimal('balance_before', 12, 2)->default(0);
            $table->decimal('balance_after', 12, 2)->default(0);
            $table->string('device_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['retailer_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('type');
            $table->index('reference_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epay_transactions');
    }
};
