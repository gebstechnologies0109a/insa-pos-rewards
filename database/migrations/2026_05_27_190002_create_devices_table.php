<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('devices')) {
            return;
        }

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('device_name')->nullable();
            $table->string('device_fingerprint', 128);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique('device_fingerprint');
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
