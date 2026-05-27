<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('epay_devices')) {
            Schema::create('epay_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('retailer_id')->nullable();
            $table->string('device_id', 100)->unique();
            $table->string('name', 150)->nullable();
            $table->enum('type', ['retailer', 'kiosk'])->default('retailer');
            $table->enum('status', ['online', 'offline', 'inactive'])->default('offline');
            $table->string('app_version', 20)->nullable();
            $table->string('os_version', 50)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('location', 255)->nullable();
            $table->string('group_zone', 100)->nullable();
            $table->json('config')->nullable();
            $table->json('enabled_services')->nullable();
            $table->string('operating_hours', 50)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
            $table->index(['retailer_id', 'status']);
            $table->index('type');
            });
        }

        if (!Schema::hasTable('epay_device_commands')) {
            Schema::create('epay_device_commands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->string('command', 100);
            $table->json('params')->nullable();
            $table->enum('status', ['pending', 'sent', 'acknowledged', 'failed', 'expired'])->default('pending');
            $table->text('result')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['device_id', 'status']);
            $table->foreign('device_id')->references('id')->on('epay_devices')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('epay_device_logs')) {
            Schema::create('epay_device_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->enum('level', ['debug', 'info', 'warning', 'error', 'critical'])->default('info');
            $table->string('tag', 100)->nullable();
            $table->text('message');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['device_id', 'level', 'created_at']);
            $table->foreign('device_id')->references('id')->on('epay_devices')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('epay_sms_logs')) {
            Schema::create('epay_sms_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id')->nullable();
            $table->enum('direction', ['incoming', 'outgoing']);
            $table->string('number', 30);
            $table->text('message');
            $table->enum('status', ['sent', 'delivered', 'received', 'failed', 'pending'])->default('pending');
            $table->string('provider', 50)->nullable();
            $table->string('reference', 100)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['device_id', 'direction', 'created_at']);
            $table->index(['number', 'created_at']);
            });
        }

        if (!Schema::hasTable('epay_commissions')) {
            Schema::create('epay_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('retailer_id')->nullable();
            $table->string('provider_code', 50)->nullable();
            $table->string('product_code', 50)->nullable();
            $table->decimal('rate', 8, 4)->default(0);
            $table->enum('type', ['percentage', 'fixed'])->default('percentage');
            $table->enum('tier', ['default', 'silver', 'gold', 'platinum'])->default('default');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['retailer_id', 'provider_code', 'product_code']);
            $table->index('tier');
            });
        }

        if (!Schema::hasTable('epay_kiosk_collections')) {
            Schema::create('epay_kiosk_collections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->decimal('amount', 12, 2);
            $table->decimal('coins_amount', 12, 2)->default(0);
            $table->decimal('bills_amount', 12, 2)->default(0);
            $table->integer('transaction_count')->default(0);
            $table->string('collected_by', 150)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();
            $table->index(['device_id', 'collected_at']);
            $table->foreign('device_id')->references('id')->on('epay_devices')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('epay_kiosk_collections');
        Schema::dropIfExists('epay_commissions');
        Schema::dropIfExists('epay_sms_logs');
        Schema::dropIfExists('epay_device_logs');
        Schema::dropIfExists('epay_device_commands');
        Schema::dropIfExists('epay_devices');
    }
};
