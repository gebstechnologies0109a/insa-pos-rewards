<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Blacklist;
use App\Models\EPayPlus\Device;
use App\Models\EPayPlus\DeviceCommand;
use App\Models\EPayPlus\DeviceLog;
use App\Models\EPayPlus\License;
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
            'machine_uid' => 'nullable|string|max:100',
            'license_code' => 'nullable|string|max:32',
            'name' => 'nullable|string|max:150',
            'type' => 'required|in:retailer,kiosk',
            'app_version' => 'nullable|string|max:20',
            'os_version' => 'nullable|string|max:50',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
        ]);

        $machineUid = $validated['machine_uid'] ?? $validated['device_id'];

        if (Blacklist::isBlocked('machine', $machineUid)) {
            return response()->json(['success' => false, 'message' => 'This machine is blocked.'], 403);
        }

        $license = null;
        if (!empty($validated['license_code'])) {
            $license = License::where('code', strtoupper(trim($validated['license_code'])))->first();

            if (!$license || $license->isBlocked()) {
                return response()->json(['success' => false, 'message' => 'Invalid or blocked license.'], 403);
            }

            if ($license->status === 'active' && $license->machine_uid && $license->machine_uid !== $machineUid) {
                return response()->json(['success' => false, 'message' => 'License already bound to another machine.'], 409);
            }

            if (!$license->isValid()) {
                return response()->json(['success' => false, 'message' => 'License is not available.'], 403);
            }
        }

        $device = Device::updateOrCreate(
            ['device_id' => $validated['device_id']],
            array_merge($validated, [
                'machine_uid' => $machineUid,
                'name' => $validated['name'] ?? ('Device ' . substr($machineUid, -6)),
                'status' => 'online',
                'last_seen_at' => now(),
                'registered_at' => now(),
            ])
        );

        if ($license) {
            $license->activate($device, $machineUid);
            if ($license->retailer_id) {
                $device->update(['retailer_id' => $license->retailer_id]);
            }
        }

        $config = $this->buildDeviceConfigPayload($device);

        return response()->json([
            'success' => true,
            'device' => [
                'id' => $device->id,
                'device_id' => $device->device_id,
                'machine_uid' => $device->machine_uid,
                'type' => $device->type,
                'license_code' => $license?->code,
                'config' => $config,
            ],
            'message' => 'Device registered successfully.',
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:100',
            'machine_uid' => 'nullable|string|max:100',
            'battery_level' => 'nullable|integer|min:0|max:100',
            'signal_strength' => 'nullable|integer',
            'network_type' => 'nullable|string|max:30',
            'app_version' => 'nullable|string|max:20',
            'os_version' => 'nullable|string|max:50',
            'uptime_seconds' => 'nullable|integer',
            'free_storage_mb' => 'nullable|integer',
            'active_transactions' => 'nullable|integer',
        ]);

        $device = Device::with('configProfile')
            ->where('device_id', $validated['device_id'])
            ->first();

        if (!$device) {
            return response()->json(['success' => false, 'message' => 'Device not found.'], 404);
        }

        if (!empty($validated['machine_uid'])) {
            $device->machine_uid = $validated['machine_uid'];
        }

        $config = $device->config ?? [];
        $config['last_heartbeat'] = [
            'battery_level' => $validated['battery_level'] ?? null,
            'signal_strength' => $validated['signal_strength'] ?? null,
            'network_type' => $validated['network_type'] ?? null,
            'app_version' => $validated['app_version'] ?? null,
            'uptime_seconds' => $validated['uptime_seconds'] ?? null,
            'free_storage_mb' => $validated['free_storage_mb'] ?? null,
            'active_transactions' => $validated['active_transactions'] ?? null,
        ];

        $updateFields = [
            'status' => 'online',
            'last_seen_at' => now(),
            'config' => $config,
        ];

        foreach (['battery_level', 'signal_strength', 'network_type', 'app_version', 'os_version', 'uptime_seconds', 'free_storage_mb'] as $field) {
            if (isset($validated[$field])) {
                $updateFields[$field] = $validated[$field];
            }
        }

        $device->update($updateFields);

        $pendingCommands = $device->commands()->where('status', 'pending')->count();
        $remoteConfig = $this->buildDeviceConfigPayload($device->fresh('configProfile'));

        return response()->json([
            'success' => true,
            'pending_commands' => $pendingCommands,
            'server_time' => now()->toIso8601String(),
            'config_version' => $device->configProfile?->updated_at?->timestamp ?? 0,
            'config' => $remoteConfig,
            'machine_uid' => $device->machine_uid,
        ]);
    }

    public function getConfig(Request $request): JsonResponse
    {
        $deviceId = $request->query('device_id');
        $machineUid = $request->query('machine_uid');

        if (!$deviceId && !$machineUid) {
            return response()->json(['success' => false, 'message' => 'device_id or machine_uid required.'], 422);
        }

        $query = Device::with('configProfile');
        if ($deviceId && $machineUid) {
            $query->where(function ($q) use ($deviceId, $machineUid) {
                $q->where('device_id', $deviceId)->orWhere('machine_uid', $machineUid);
            });
        } elseif ($deviceId) {
            $query->where('device_id', $deviceId);
        } else {
            $query->where('machine_uid', $machineUid);
        }

        $device = $query->first();

        if (!$device) {
            return response()->json(['success' => false, 'message' => 'Device not found.'], 404);
        }

        $payload = $this->buildDeviceConfigPayload($device);

        return response()->json(array_merge(['success' => true], $payload));
    }

    /**
     * Build remote kiosk config from profile + device overrides.
     */
    protected function buildDeviceConfigPayload(Device $device): array
    {
        $profileSettings = $device->configProfile?->settings ?? [];
        $services = $profileSettings['services'] ?? null;

        $enabled = $device->enabled_services ?? [];
        if (empty($enabled) && $services) {
            $enabled = collect($services)->filter()->keys()->values()->all();
        }
        if (empty($enabled)) {
            $enabled = ['eload', 'bills', 'ecash', 'gcash', 'maya'];
        }

        return [
            'config' => array_merge($device->config ?? [], $profileSettings),
            'enabled_services' => $enabled,
            'services' => $services ?? [
                'eload' => in_array('eload', $enabled),
                'bills' => in_array('bills', $enabled),
                'gcash' => in_array('gcash', $enabled) || in_array('ecash', $enabled),
                'maya' => in_array('maya', $enabled) || in_array('ecash', $enabled),
                'ecash' => in_array('ecash', $enabled),
            ],
            'operating_hours' => $device->operating_hours,
            'is_locked' => (bool) $device->is_locked,
            'type' => $device->type,
            'machine_uid' => $device->machine_uid,
            'config_version' => $device->configProfile?->updated_at?->timestamp ?? 0,
        ];
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
