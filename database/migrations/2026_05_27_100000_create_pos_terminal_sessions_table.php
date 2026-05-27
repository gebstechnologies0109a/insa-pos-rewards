<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_terminal_sessions')) {
            return;
        }

        Schema::create('pos_terminal_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('user_id');
            $table->string('device_fingerprint', 128);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
            $table->index(['user_id', 'is_active']);
            $table->index('device_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_terminal_sessions');
    }
};
