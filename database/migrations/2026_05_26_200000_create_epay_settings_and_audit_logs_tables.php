<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('epay_settings')) {
            Schema::create('epay_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('epay_audit_logs')) {
            Schema::create('epay_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 100);
                $table->string('model_type')->nullable();
                $table->unsignedBigInteger('model_id')->nullable();
                $table->text('description')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['action', 'created_at']);
                $table->index(['model_type', 'model_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('epay_audit_logs');
        Schema::dropIfExists('epay_settings');
    }
};
