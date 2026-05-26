<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epay_providers', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // ELOAD, BILLS, ECASH, WIFI
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('sms_number')->nullable();
            $table->string('sms_format')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epay_providers');
    }
};
