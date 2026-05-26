<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Device;
use App\Models\EPayPlus\DeviceCommand;
use App\Models\EPayPlus\DeviceLog;
use App\Models\EPayPlus\SmsLog;
use App\Models\EPayPlus\EPaySetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DeviceApiController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:100',
            'name' => 'nullable|string|max:150',
            'type' => 'required|in:retailer,kiosk',
            'app_version' => 'nullable|string|max:20',
            'os_version' => 'nullable|string|max:50',
            'model' => 'nullable|string|max:100',
        ]);

        $device = Device::updateOrCreate(
            ['device_id' => $validated['device_id']],
            array_merge($validated, [
                'status' => 'online',
                'last_seen_at' => now(),
                'registered_at' => now(),
            ])
        );

        return response()->json([
            'success' => true,
            'device' => [
                'id' => $device->id,
                'device_id' => $device->device_id,
                'type' => $device->type,
                'config' => $device->config,
            ],
            'message' => 'Device registered successfully.',
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:100',
            'battery_level' => 'nullable|integer',
            'signal_strength' => 'nullable|integer',
            'uptime_seconds' => 'nullable|integer',
            'free_storage_mb' => 'nullable|integer',
            'active_transactions' => 'nullable|integer',
        ]);

        $device = Device::where('device_id', $validated['device_id'])->first();

        if (!$device) {
            return response()->json(['success' => false, 'message' => 'Device not found.'], 404);
        }

        $config = $device->config ?? [];
        $config['last_heartbeat'] = [
            'battery_level' => $validated['battery_level'] ?? null,
            'signal_strength' => $validated['signal_strength'] ?? null,
            'uptime_seconds' => $validated['uptime_seconds'] ?? null,
            'free_storage_mb' => $validated['free_storage_mb'] ?? null,
            'active_transactions' => $validated['active_transactions'] ?? null,
        ];

        $device->update([
            'status' => 'online',
            'last_seen_at' => now(),
            'config' => $config,
        ]);

        $pendingCommands = $device->commands()->where('status', 'pending')->count();

        return response()->json([
            'success' => true,
            'pending_commands' => $pendingCommands,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function getConfig(Request $request): JsonResponse
    {
        $deviceId = $request->query('device_id');
        $device = Device::where('device_id', $deviceId)->first();

        if (!$device) {
            return response()->json(['success' => false, 'message' => 'Device not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'config' => $device->config ?? [],
            'enabled_services' => $device->enabled_services ?? ['eload', 'bills', 'ecash', 'wifi'],
            'operating_hours' => $device->operating_hours,
            'type' => $device->type,
        ]);
    }

    public function log(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:100',
            'logs' => 'required|array|max:100',
            'logs.*.level' => 'required|in:debug,info,warning,error,critical',
            'logs.*.tag' => 'nullable|string|max:100',
            'logs.*.message' => 'required|string|max:2000',
            'logs.*.meta' => 'nullable|array',
            'logs.*.timestamp' => 'nullable|string',
        ]);

        $device = Device::where('device_id', $validated['device_id'])->first();

        if (!$device) {
            return response()->json(['success' => false, 'message' => 'Device not found.'], 404);
        }

        $entries = [];
        foreach ($validated['logs'] as $log) {
            $entries[] = [
                'device_id' => $device->id,
                'level' => $log['level'],
                'tag' => $log['tag'] ?? null,
                'message' => $log['message'],
                'meta' => isset($log['meta']) ? json_encode($log['meta']) : null,
                'created_at' => $log['timestamp'] ?? now(),
            ];
        }

        DeviceLog::insert($entries);

        return response()->json([
            'success' => true,
            'count' => count($entries),
        ]);
    }

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

        $device->commands()
            ->where('status', 'pending')
            ->whereIn('id', $commands->pluck('id'))
            ->update(['status' => 'sent', 'sent_at' => now()]);

        return response()->json([
            'success' => true,
            'commands' => $commands,
        ]);
    }

    public function acknowledgeCommand(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command_id' => 'required|integer',
            'status' => 'required|in:acknowledged,failed',
            'result' => 'nullable|string|max:1000',
        ]);

        $command = DeviceCommand::find($validated['command_id']);

        if (!$command) {
            return response()->json(['success' => false, 'message' => 'Command not found.'], 404);
        }

        $command->update([
            'status' => $validated['status'],
            'result' => $validated['result'] ?? null,
            'executed_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function syncTransactions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:100',
            'transactions' => 'required|array|max:200',
            'transactions.*.local_id' => 'required|integer',
            'transactions.*.type' => 'required|string|max:50',
            'transactions.*.provider_code' => 'required|string|max:50',
            'transactions.*.product_code' => 'nullable|string|max:50',
            'transactions.*.target_number' => 'required|string|max:50',
            'transactions.*.amount' => 'required|numeric',
            'transactions.*.reference_number' => 'nullable|string|max:100',
            'transactions.*.status' => 'required|string|max:30',
            'transactions.*.created_at' => 'required|string',
        ]);

        $syncedCount = count($validated['transactions']);

        return response()->json([
            'success' => true,
            'synced_count' => $syncedCount,
            'message' => "{$syncedCount} transactions synced.",
        ]);
    }

    public function getProviders(Request $request): JsonResponse
    {
        $providers = EPaySetting::where('key', 'like', 'provider_%')->get()
            ->map(fn($s) => json_decode($s->value, true))
            ->filter();

        return response()->json([
            'success' => true,
            'providers' => $providers->values(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function getSystemConfig(Request $request): JsonResponse
    {
        $settings = EPaySetting::whereIn('key', [
            'commission_rates', 'enabled_services', 'maintenance_mode',
            'min_balance_alert', 'sms_templates', 'sms_providers',
        ])->pluck('value', 'key')->map(fn($v) => json_decode($v, true) ?? $v);

        return response()->json([
            'success' => true,
            'config' => $settings,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function reportSms(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:100',
            'direction' => 'required|in:incoming,outgoing',
            'number' => 'required|string|max:30',
            'message' => 'required|string|max:2000',
            'status' => 'required|in:sent,delivered,received,failed',
            'reference' => 'nullable|string|max:100',
        ]);

        $device = Device::where('device_id', $validated['device_id'])->first();

        SmsLog::create([
            'device_id' => $device?->id,
            'direction' => $validated['direction'],
            'number' => $validated['number'],
            'message' => $validated['message'],
            'status' => $validated['status'],
            'reference' => $validated['reference'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }
}
