<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('expiry_alerts')) {
            return;
        }

        Schema::create('expiry_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_batch_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('product_id');
            $table->string('alert_type'); // thirty_day, seven_day, expired
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->timestamp('handled_at')->nullable();
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamps();

            $table->unique(['inventory_batch_id', 'alert_type']);
            $table->index(['branch_id', 'alert_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expiry_alerts');
    }
};
