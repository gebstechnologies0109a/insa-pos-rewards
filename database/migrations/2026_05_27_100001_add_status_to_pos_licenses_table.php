<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_licenses', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('pos_slots');
        });

        DB::table('pos_licenses')->where('active', true)->update(['status' => 'active']);
        DB::table('pos_licenses')->where('active', false)->update(['status' => 'suspended']);
    }

    public function down(): void
    {
        Schema::table('pos_licenses', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
