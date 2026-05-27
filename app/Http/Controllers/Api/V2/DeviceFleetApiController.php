<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Device;
use App\Models\EPayPlus\DeviceAlert;
use App\Models\EPayPlus\DeviceCommand;
use App\Models\EPayPlus\DeviceConfig;
use App\Models\EPayPlus\DeviceUpdateStatus;
use App\Models\EPayPlus\OtaUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceFleetApiController extends Controller
{
    /**
     * Enhanced heartbeat with full device telemetry.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:100',
            'battery_level' => 'nullable|integer|min:0|max:100',
            'signal_strength' => 'nullable|integer',
            'network_type' => 'nullable|string|max:30',
            'uptime_seconds' => 'nullable|integer',
            'free_storage_mb' => 'nullable|integer',
            'app_version' => 'nullable|string|max:20',
            'os_version' => 'nullable|string|max:50',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'ip_address' => 'nullable|string|max:45',
            'active_transactions' => 'nullable|integer',
        ]);

        $device = Device::where('device_id', $validated['device_id'])->first();

        if (!$device) {
            return response()->json(['success' => false, 'message' => 'Device not found.'], 404);
        }

        $wasOffline = !$device->isOnline();

        $updateData = [
            'status' => 'online',
            'last_seen_at' => now(),
        ];

        $fields = ['battery_level', 'signal_strength', 'network_type', 'uptime_seconds',
                   'free_storage_mb', 'app_version', 'os_version', 'latitude', 'longitude', 'ip_address'];

        foreach ($fields as $field) {
            if (isset($validated[$field])) {
                $updateData[$field] = $validated[$field];
            }
        }

        $device->update($updateData);

        // Auto-resolve offline alerts when device comes back
        if ($wasOffline) {
            $device->alerts()
                ->where('type', 'offline')
                ->where('status', 'active')
                ->update([
                    'status' => 'auto_resolved',
                    'resolved_at' => now(),
                    'resolved_by' => 'system',
                ]);
        }

        // Check for low battery alert
        if (isset($validated['battery_level']) && $validated['battery_level'] <= 15) {
            $existingAlert = $device->alerts()
                ->where('type', 'low_battery')
                ->where('status', 'active')
                ->first();

            if (!$existingAlert) {
                DeviceAlert::create([
                    'device_id' => $device->id,
                    'type' => 'low_battery',
                    'severity' => $validated['battery_level'] <= 5 ? 'critical' : 'warning',
                    'title' => "Low battery on {$device->name}",
                    'message' => "Battery level: {$validated['battery_level']}%",
                    'meta' => ['battery_level' => $validated['battery_level']],
                ]);
            }
        }

        $pendingCommands = $device->commands()->where('status', 'pending')->count();

        return response()->json([
            'success' => true,
            'pending_commands' => $pendingCommands,
            'server_time' => now()->toIso8601String(),
            'config_version' => $device->configProfile?->updated_at?->timestamp,
        ]);
    }

    /**
     * Fetch pending commands for this device.
     */
    public function getCommands(Request $request): JsonResponse
    {
        $deviceId = $request->query('device_id');
        $device = Device::where('device_id', $deviceId)->first();

        if (!$device) {
            return response()->json(['success' => false, 'message' => 'Device not found.'], 404);
        }

        $commands = $device->commands()
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderBy('created_at')
            ->get(['id', 'command', 'params', 'created_at']);

        // Mark as sent
        if ($commands->isNotEmpty()) {
            $device->commands()
                ->whereIn('id', $commands->pluck('id'))
                ->update(['status' => 'sent', 'sent_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'commands' => $commands,
        ]);
    }

    /**
     * Acknowledge command receipt.
     */
    public function acknowledgeCommand(Request $request, int $commandId): JsonResponse
    {
        $command = DeviceCommand::find($commandId);

        if (!$command) {
            return response()->json(['success' => false, 'message' => 'Command not found.'], 404);
        }

        $command->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Report command execution result.
     */
    public function commandResult(Request $request, int $commandId): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:executed,failed',
            'result' => 'nullable|string|max:2000',
        ]);

        $command = DeviceCommand::find($commandId);

        if (!$command) {
            return response()->json(['success' => false, 'message' => 'Command not found.'], 404);
        }

        $command->update([
            'status' => $validated['status'] === 'executed' ? 'executed' : 'failed',
            'result' => $validated['result'] ?? null,
            'executed_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Fetch device configuration profile.
     */
    public function getConfig(Request $request): JsonResponse
    {
        $deviceId = $request->query('device_id');
        $device = Device::with('configProfile')->where('device_id', $deviceId)->first();

        if (!$device) {
            return response()->json(['success' => false, 'message' => 'Device not found.'], 404);
        }

        $config = $device->configProfile?->settings ?? $device->config ?? [];

        return response()->json([
            'success' => true,
            'config' => $config,
            'enabled_services' => $device->enabled_services ?? ['eload', 'bills', 'ecash', 'wifi'],
            'operating_hours' => $device->operating_hours,
            'is_locked' => $device->is_locked,
            'config_version' => $device->configProfile?->updated_at?->timestamp ?? 0,
        ]);
    }

    /**
     * Check for available OTA updates.
     */
    public function checkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:100',
            'current_version' => 'required|string|max:30',
        ]);

        $device = Device::where('device_id', $validated['device_id'])->first();

        if (!$device) {
            return response()->json(['success' => false, 'message' => 'Device not found.'], 404);
        }

        // Find pending update for this device
        $pendingUpdate = DeviceUpdateStatus::where('device_id', $device->id)
            ->where('status', 'pending')
            ->with('otaUpdate')
            ->first();

        if (!$pendingUpdate || !$pendingUpdate->otaUpdate) {
            return response()->json([
                'success' => true,
                'update_available' => false,
            ]);
        }

        $ota = $pendingUpdate->otaUpdate;

        if ($ota->status !== 'active') {
            return response()->json([
                'success' => true,
                'update_available' => false,
            ]);
        }

        $pendingUpdate->update(['status' => 'downloading', 'started_at' => now()]);

        return response()->json([
            'success' => true,
            'update_available' => true,
            'update' => [
                'id' => $ota->id,
                'version' => $ota->version,
                'download_url' => url("storage/{$ota->file_path}"),
                'file_size' => $ota->file_size,
                'checksum' => $ota->checksum,
                'release_notes' => $ota->release_notes,
            ],
        ]);
    }

    /**
     * Report OTA update result.
     */
    public function reportUpdateResult(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:100',
            'ota_update_id' => 'required|integer|exists:epay_ota_updates,id',
            'status' => 'required|in:success,failed',
            'error_message' => 'nullable|string|max:1000',
            'new_version' => 'nullable|string|max:30',
        ]);

        $device = Device::where('device_id', $validated['device_id'])->first();

        if (!$device) {
            return response()->json(['success' => false, 'message' => 'Device not found.'], 404);
        }

        $updateStatus = DeviceUpdateStatus::where('device_id', $device->id)
            ->where('ota_update_id', $validated['ota_update_id'])
            ->first();

        if ($updateStatus) {
            $updateStatus->update([
                'status' => $validated['status'],
                'error_message' => $validated['error_message'] ?? null,
                'completed_at' => now(),
            ]);
        }

        $ota = OtaUpdate::find($validated['ota_update_id']);
        if ($ota) {
            if ($validated['status'] === 'success') {
                $ota->increment('success_count');
                $ota->decrement('pending_count');
                $device->update(['current_ota_version' => $validated['new_version'] ?? $ota->version]);
            } else {
                $ota->increment('failure_count');
                $ota->decrement('pending_count');

                DeviceAlert::create([
                    'device_id' => $device->id,
                    'type' => 'update_failed',
                    'severity' => 'warning',
                    'title' => "OTA update failed on {$device->name}",
                    'message' => $validated['error_message'] ?? "Update to v{$ota->version} failed",
                    'meta' => ['ota_update_id' => $ota->id, 'version' => $ota->version],
                ]);
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Device reports an alert condition.
     */
    public function reportAlert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:100',
            'type' => 'required|string|max:50',
            'severity' => 'required|in:info,warning,critical',
            'title' => 'required|string|max:200',
            'message' => 'nullable|string|max:2000',
            'meta' => 'nullable|array',
        ]);

        $device = Device::where('device_id', $validated['device_id'])->first();

        if (!$device) {
            return response()->json(['success' => false, 'message' => 'Device not found.'], 404);
        }

        DeviceAlert::create([
            'device_id' => $device->id,
            'type' => $validated['type'],
            'severity' => $validated['severity'],
            'title' => $validated['title'],
            'message' => $validated['message'] ?? null,
            'meta' => $validated['meta'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }
}
