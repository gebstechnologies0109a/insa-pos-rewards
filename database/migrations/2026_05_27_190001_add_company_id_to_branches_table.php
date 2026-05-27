<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies') || Schema::hasColumn('branches', 'company_id')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
        });

        $defaultCompanyId = DB::table('companies')->insertGetId([
            'name'       => 'GEBS',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('branches')->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);

        Schema::table('branches', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->index('company_id');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('branches', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('branches', 'company_id')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
