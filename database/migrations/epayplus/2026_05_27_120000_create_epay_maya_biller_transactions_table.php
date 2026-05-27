<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epay_maya_biller_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('request_reference_no')->unique();
            $table->string('maya_transaction_id')->nullable()->index();
            $table->enum('state', [
                'NEW',
                'PROCESSING',
                'AUTHORIZED',
                'POSTING',
                'FAILED',
                'FULFILLED',
                'POSTING_FAILED',
            ])->default('NEW');
            $table->string('biller_code');
            $table->string('account_number');
            $table->decimal('amount', 12, 2);
            $table->decimal('fee', 12, 2)->default(0);
            $table->string('currency', 3)->default('PHP');
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->json('raw_validate_payload')->nullable();
            $table->json('raw_post_payload')->nullable();
            $table->timestamp('callback_sent_at')->nullable();
            $table->json('callback_response')->nullable();
            $table->foreignId('epay_transaction_id')
                ->nullable()
                ->constrained('epay_transactions')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['state', 'created_at']);
            $table->index('biller_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epay_maya_biller_transactions');
    }
};
