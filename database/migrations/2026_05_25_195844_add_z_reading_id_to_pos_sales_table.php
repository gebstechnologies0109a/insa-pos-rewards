<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->unsignedBigInteger('z_reading_id')->nullable()->after('shift_id');
            $table->index('z_reading_id');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropIndex(['z_reading_id']);
            $table->dropColumn('z_reading_id');
        });
    }
};
