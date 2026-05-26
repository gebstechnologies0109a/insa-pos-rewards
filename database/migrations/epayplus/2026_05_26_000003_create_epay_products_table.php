<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epay_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('epay_providers')->cascadeOnDelete();
            $table->string('type'); // ELOAD, BILLS, ECASH
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('retailer_price', 10, 2)->default(0);
            $table->decimal('fee', 10, 2)->default(0);
            $table->decimal('commission', 10, 2)->default(0);
            $table->string('description')->nullable();
            $table->string('keyword')->nullable();
            $table->string('sms_format')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->integer('validity_days')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index('provider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epay_products');
    }
};
