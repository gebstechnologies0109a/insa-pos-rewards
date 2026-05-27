<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Device Groups
        Schema::create('epay_device_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->string('location', 255)->nullable();
            $table->string('color', 7)->default('#6c757d');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Device Configuration Profiles
        Schema::create('epay_device_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->json('settings');
            $table->boolean('is_default')->default(false);
            $table->integer('device_count')->default(0);
            $table->timestamps();
        });

        // OTA Updates
        Schema::create('epay_ota_updates', function (Blueprint $table) {
            $table->id();
            $table->string('version', 30);
            $table->string('filename', 255);
            $table->string('file_path', 500);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->text('release_notes')->nullable();
            $table->enum('rollout_type', ['all', 'staged', 'group'])->default('all');
            $table->unsignedTinyInteger('rollout_percentage')->default(100);
            $table->unsignedBigInteger('target_group_id')->nullable();
            $table->enum('status', ['draft', 'active', 'paused', 'completed', 'rolled_back'])->default('draft');
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->unsignedInteger('pending_count')->default(0);
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->foreign('target_group_id')->references('id')->on('epay_device_groups')->nullOnDelete();
        });

        // Device Alerts
        Schema::create('epay_device_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->string('type', 50); // offline, low_balance, low_battery, suspicious_activity, update_failed
            $table->string('severity', 20)->default('warning'); // info, warning, critical
            $table->string('title', 200);
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->enum('status', ['active', 'acknowledged', 'resolved', 'auto_resolved'])->default('active');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by', 100)->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('epay_devices')->cascadeOnDelete();
            $table->index(['status', 'severity']);
            $table->index(['device_id', 'type']);
        });

        // Add new columns to epay_devices
        Schema::table('epay_devices', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->after('group_zone');
            $table->unsignedBigInteger('config_profile_id')->nullable()->after('group_id');
            $table->decimal('latitude', 10, 7)->nullable()->after('location');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedTinyInteger('battery_level')->nullable()->after('longitude');
            $table->string('network_type', 30)->nullable()->after('battery_level');
            $table->smallInteger('signal_strength')->nullable()->after('network_type');
            $table->string('serial_number', 100)->nullable()->after('model');
            $table->unsignedInteger('free_storage_mb')->nullable()->after('signal_strength');
            $table->unsignedBigInteger('uptime_seconds')->nullable()->after('free_storage_mb');
            $table->string('current_ota_version', 30)->nullable()->after('app_version');
            $table->string('ip_address', 45)->nullable()->after('uptime_seconds');
            $table->string('mac_address', 17)->nullable()->after('ip_address');
            $table->boolean('is_locked')->default(false)->after('status');

            $table->foreign('group_id')->references('id')->on('epay_device_groups')->nullOnDelete();
            $table->foreign('config_profile_id')->references('id')->on('epay_device_configs')->nullOnDelete();
        });

        // Add new columns to epay_device_commands for enhanced tracking
        Schema::table('epay_device_commands', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->after('device_id');
            $table->string('source', 50)->default('manual')->after('command'); // manual, scheduled, system
            $table->timestamp('acknowledged_at')->nullable()->after('sent_at');
            $table->unsignedBigInteger('scheduled_by')->nullable()->after('expires_at');
            $table->string('schedule_cron', 100)->nullable()->after('scheduled_by');
            $table->boolean('is_bulk')->default(false)->after('schedule_cron');
        });

        // Device update tracking (per-device OTA status)
        Schema::create('epay_device_update_status', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->unsignedBigInteger('ota_update_id');
            $table->enum('status', ['pending', 'downloading', 'installing', 'success', 'failed', 'skipped'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('epay_devices')->cascadeOnDelete();
            $table->foreign('ota_update_id')->references('id')->on('epay_ota_updates')->cascadeOnDelete();
            $table->unique(['device_id', 'ota_update_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epay_device_update_status');

        Schema::table('epay_device_commands', function (Blueprint $table) {
            $table->dropColumn(['group_id', 'source', 'acknowledged_at', 'scheduled_by', 'schedule_cron', 'is_bulk']);
        });

        Schema::table('epay_devices', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropForeign(['config_profile_id']);
            $table->dropColumn([
                'group_id', 'config_profile_id', 'latitude', 'longitude',
                'battery_level', 'network_type', 'signal_strength', 'serial_number',
                'free_storage_mb', 'uptime_seconds', 'current_ota_version',
                'ip_address', 'mac_address', 'is_locked',
            ]);
        });

        Schema::dropIfExists('epay_device_alerts');
        Schema::dropIfExists('epay_ota_updates');
        Schema::dropIfExists('epay_device_configs');
        Schema::dropIfExists('epay_device_groups');
    }
};
