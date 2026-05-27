<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_terminal_sessions') || Schema::hasColumn('pos_terminal_sessions', 'device_id')) {
            return;
        }

        Schema::table('pos_terminal_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('device_id')->nullable()->after('branch_id');
            $table->foreign('device_id')->references('id')->on('devices')->nullOnDelete();
            $table->index('device_id');
        });

        if (! Schema::hasTable('devices')) {
            return;
        }

        $sessions = DB::table('pos_terminal_sessions')
            ->whereNotNull('device_fingerprint')
            ->where('device_fingerprint', '!=', '')
            ->get(['id', 'branch_id', 'device_fingerprint']);

        foreach ($sessions as $session) {
            $deviceId = DB::table('devices')
                ->where('branch_id', $session->branch_id)
                ->where('device_fingerprint', $session->device_fingerprint)
                ->value('id');

            if ($deviceId) {
                DB::table('pos_terminal_sessions')
                    ->where('id', $session->id)
                    ->update(['device_id' => $deviceId]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('pos_terminal_sessions', 'device_id')) {
            return;
        }

        Schema::table('pos_terminal_sessions', function (Blueprint $table) {
            $table->dropForeign(['device_id']);
            $table->dropColumn('device_id');
        });
    }
};
