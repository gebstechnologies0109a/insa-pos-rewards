<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epay_providers', function (Blueprint $table) {
            $table->string('billing_type', 20)->nullable()->after('category'); // prepaid | postpaid
        });

        Schema::table('epay_products', function (Blueprint $table) {
            $table->string('billing_type', 20)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('epay_products', function (Blueprint $table) {
            $table->dropColumn('billing_type');
        });

        Schema::table('epay_providers', function (Blueprint $table) {
            $table->dropColumn('billing_type');
        });
    }
};
