<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_in_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_in_id');
            $table->unsignedBigInteger('product_id');
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->decimal('qty', 12, 3)->default(0);
            $table->decimal('cost', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['stock_in_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_in_items');
    }
};
