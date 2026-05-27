<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epay_products', function (Blueprint $table) {
            $table->string('product_kind', 20)->default('regular')->after('billing_type');
            $table->index(['type', 'product_kind', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('epay_products', function (Blueprint $table) {
            $table->dropIndex(['type', 'product_kind', 'is_active']);
            $table->dropColumn('product_kind');
        });
    }
};
