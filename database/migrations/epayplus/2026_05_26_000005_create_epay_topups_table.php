<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epay_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retailer_id')->constrained('epay_retailers')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method'); // GCASH, BANK_TRANSFER, CASH, MAYA
            $table->string('reference_number')->nullable();
            $table->string('proof_url')->nullable();
            $table->string('status')->default('PENDING'); // PENDING, APPROVED, REJECTED
            $table->string('remarks')->nullable();
            $table->decimal('balance_before', 12, 2)->default(0);
            $table->decimal('balance_after', 12, 2)->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['retailer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epay_topups');
    }
};
