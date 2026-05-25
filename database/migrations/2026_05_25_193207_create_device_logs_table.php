<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('device_logs', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 100)->nullable()->index();
            $table->string('device_model', 150)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->string('android_version', 30)->nullable();
            $table->string('level', 20)->default('info')->index();
            $table->string('tag', 100)->nullable();
            $table->text('message');
            $table->text('url')->nullable();
            $table->text('extra')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_logs');
    }
};
