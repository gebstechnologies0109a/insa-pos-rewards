<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reward_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('sale_id');
            $table->string('type');
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index(['member_id', 'type']);
            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_transactions');
    }
};
