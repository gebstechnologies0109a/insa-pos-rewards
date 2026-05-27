<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dual-wallet columns on retailers (E-Load + Bills/Cash-In)
        Schema::table('epay_retailers', function (Blueprint $table) {
            $table->decimal('eload_balance', 12, 2)->default(0)->after('balance');
            $table->decimal('bills_balance', 12, 2)->default(0)->after('eload_balance');
        });

        // Migrate existing single balance into E-Load wallet
        DB::table('epay_retailers')->where('eload_balance', 0)->update([
            'eload_balance' => DB::raw('balance'),
        ]);

        // Machine UID mapping (09NET* / EPAY* style identifiers)
        Schema::table('epay_devices', function (Blueprint $table) {
            $table->string('machine_uid', 100)->nullable()->unique()->after('device_id');
            $table->unsignedBigInteger('license_id')->nullable()->after('retailer_id');
        });

        Schema::create('epay_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->enum('type', ['retailer', 'kiosk'])->default('retailer');
            $table->enum('status', ['available', 'active', 'revoked', 'blocked', 'expired'])->default('available');
            $table->unsignedBigInteger('retailer_id')->nullable();
            $table->unsignedBigInteger('device_id')->nullable();
            $table->string('machine_uid', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index('machine_uid');
        });

        Schema::create('epay_blacklists', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['phone', 'account', 'device', 'machine'])->default('phone');
            $table->string('value', 150);
            $table->string('reason', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('blocked_by', 100)->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'value']);
            $table->index(['type', 'is_active']);
        });

        Schema::create('epay_product_pricing', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_code', 50)->nullable();
            $table->unsignedBigInteger('retailer_id')->nullable();
            $table->enum('discount_type', ['percentage', 'fixed', 'override'])->default('percentage');
            $table->decimal('discount_value', 10, 4)->default(0);
            $table->decimal('custom_price', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_code', 'retailer_id']);
            $table->index(['product_id', 'retailer_id']);
        });

        // Seed default kiosk config profile if table exists
        if (Schema::hasTable('epay_device_configs') && DB::table('epay_device_configs')->count() === 0) {
            DB::table('epay_device_configs')->insert([
                'name' => 'Default Kiosk',
                'description' => 'Standard service toggles for fleet devices',
                'settings' => json_encode([
                    'services' => [
                        'eload' => true,
                        'bills' => true,
                        'gcash' => true,
                        'maya' => true,
                        'ecash' => true,
                    ],
                    'heartbeat_interval_sec' => 60,
                    'command_poll_sec' => 60,
                ]),
                'is_default' => true,
                'device_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('epay_product_pricing');
        Schema::dropIfExists('epay_blacklists');
        Schema::dropIfExists('epay_licenses');

        Schema::table('epay_devices', function (Blueprint $table) {
            $table->dropColumn(['machine_uid', 'license_id']);
        });

        Schema::table('epay_retailers', function (Blueprint $table) {
            $table->dropColumn(['eload_balance', 'bills_balance']);
        });
    }
};
