<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epay_retail_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retailer_id')->constrained('epay_retailers')->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('sku', 64)->nullable();
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->string('category')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['retailer_id', 'is_active']);
        });

        Schema::create('epay_pos_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retailer_id')->constrained('epay_retailers')->cascadeOnDelete();
            $table->string('reference', 32)->unique();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('payment_method', 50)->default('cash');
            $table->string('source', 20)->default('android');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['retailer_id', 'created_at']);
        });

        Schema::create('epay_pos_sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_sale_id')->constrained('epay_pos_sales')->cascadeOnDelete();
            $table->string('product_type', 20);
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name');
            $table->string('sku', 64)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epay_pos_sale_lines');
        Schema::dropIfExists('epay_pos_sales');
        Schema::dropIfExists('epay_retail_products');
    }
};
