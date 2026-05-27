<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epay_maya_checkout_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('checkout_id')->nullable()->index();
            $table->string('reference_number')->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('PHP');
            $table->string('status', 32)->default('pending');
            $table->string('redirect_url', 512)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('webhook_payload')->nullable();
            $table->unsignedBigInteger('retailer_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epay_maya_checkout_sessions');
    }
};
