<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epay_retailers', function (Blueprint $table) {
            $table->id();
            $table->string('account_id')->unique();
            $table->string('business_name');
            $table->string('owner_name');
            $table->string('mobile_number', 15);
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->decimal('balance', 12, 2)->default(0);
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->string('pin')->comment('Hashed PIN');
            $table->string('api_token', 80)->nullable()->unique();
            $table->string('device_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_kiosk_enabled')->default(false);
            $table->string('kiosk_pin')->nullable();
            $table->string('printer_address')->nullable();
            $table->string('printer_type')->default('BLUETOOTH');
            $table->string('server_url')->nullable();
            $table->integer('sim_slot')->default(0);
            $table->json('settings')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epay_retailers');
    }
};
