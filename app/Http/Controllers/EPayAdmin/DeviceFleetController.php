<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Device;
use App\Models\EPayPlus\DeviceAlert;
use App\Models\EPayPlus\DeviceCommand;
use App\Models\EPayPlus\DeviceConfig;
use App\Models\EPayPlus\DeviceGroup;
use App\Models\EPayPlus\DeviceUpdateStatus;
use App\Models\EPayPlus\OtaUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DeviceFleetController extends Controller
{
    public function dashboard(Request $request)
    {
        $query = Device::with('group');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('device_id', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('group_id')) {
            $query->where('group_id', $request->group_id);
        }

        if ($request->filled('status')) {
            if ($request->status === 'online') {
                $query->where('last_seen_at', '>=', now()->subMinutes(5));
            } elseif ($request->status === 'warning') {
                $query->where('last_seen_at', '<', now()->subMinutes(5))
                      ->where('last_seen_at', '>=', now()->subMinutes(30));
            } elseif ($request->status === 'offline') {
                $query->where(function ($q) {
                    $q->whereNull('last_seen_at')
                      ->orWhere('last_seen_at', '<', now()->subMinutes(30));
                });
            }
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $devices = $query->orderByDesc('last_seen_at')->paginate(24)->withQueryString();
        $groups = DeviceGroup::where('is_active', true)->get();

        $stats = [
            'total' => Device::count(),
            'online' => Device::where('last_seen_at', '>=', now()->subMinutes(5))->count(),
            'offline' => Device::where(function ($q) {
                $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subMinutes(5));
            })->count(),
            'alerts' => DeviceAlert::where('status', 'active')->count(),
            'locked' => Device::where('is_locked', true)->count(),
        ];

        return view('epayplus.fleet.dashboard', compact('devices', 'groups', 'stats'));
    }

    public function deviceDetail(Device $device)
    {
        $device->load(['group', 'configProfile', 'retailer']);

        $recentCommands = $device->commands()
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $recentLogs = $device->logs()
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $activeAlerts = $device->alerts()
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->get();

        $collections = $device->collections()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $groups = DeviceGroup::where('is_active', true)->get();
        $configs = DeviceConfig::all();

        return view('epayplus.fleet.device-detail', compact(
            'device', 'recentCommands', 'recentLogs',
            'activeAlerts', 'collections', 'groups', 'configs'
        ));
    }

    public function sendCommand(Request $request, Device $device)
    {
        $validated = $request->validate([
            'command' => 'required|string|max:100',
            'params' => 'nullable|array',
        ]);

        DeviceCommand::create([
            'device_id' => $device->id,
            'command' => $validated['command'],
            'params' => $validated['params'] ?? null,
            'status' => 'pending',
            'source' => 'manual',
            'expires_at' => now()->addHours(24),
        ]);

        return back()->with('success', "Command '{$validated['command']}' queued for {$device->name}.");
    }

    public function bulkCommand(Request $request)
    {
        $validated = $request->validate([
            'command' => 'required|string|max:100',
            'params' => 'nullable|array',
            'group_id' => 'nullable|integer|exists:epay_device_groups,id',
            'device_ids' => 'nullable|array',
            'device_ids.*' => 'integer|exists:epay_devices,id',
        ]);

        $query = Device::query();
        if (!empty($validated['group_id'])) {
            $query->where('group_id', $validated['group_id']);
        } elseif (!empty($validated['device_ids'])) {
            $query->whereIn('id', $validated['device_ids']);
        } else {
            return back()->with('error', 'Select a group or specific devices.');
        }

        $devices = $query->get();
        $count = 0;

        foreach ($devices as $device) {
            DeviceCommand::create([
                'device_id' => $device->id,
                'command' => $validated['command'],
                'params' => $validated['params'] ?? null,
                'status' => 'pending',
                'source' => 'manual',
                'is_bulk' => true,
                'group_id' => $validated['group_id'] ?? null,
                'expires_at' => now()->addHours(24),
            ]);
            $count++;
        }

        return back()->with('success', "Command '{$validated['command']}' queued for {$count} devices.");
    }

    public function updateDevice(Request $request, Device $device)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'group_id' => 'nullable|integer|exists:epay_device_groups,id',
            'config_profile_id' => 'nullable|integer|exists:epay_device_configs,id',
            'location' => 'nullable|string|max:255',
        ]);

        $device->update($validated);

        return back()->with('success', 'Device updated successfully.');
    }

    // ─── OTA Updates ────────────────────────────────────────────

    public function updates(Request $request)
    {
        $updates = OtaUpdate::orderByDesc('created_at')->paginate(15);
        $groups = DeviceGroup::where('is_active', true)->get();
        $totalDevices = Device::count();

        return view('epayplus.fleet.updates', compact('updates', 'groups', 'totalDevices'));
    }

    public function storeUpdate(Request $request)
    {
        $validated = $request->validate([
            'version' => 'required|string|max:30|unique:epay_ota_updates,version',
            'apk_file' => 'required|file|mimes:apk|max:102400',
            'release_notes' => 'nullable|string|max:2000',
            'rollout_type' => 'required|in:all,staged,group',
            'rollout_percentage' => 'required_if:rollout_type,staged|integer|min:1|max:100',
            'target_group_id' => 'required_if:rollout_type,group|nullable|integer|exists:epay_device_groups,id',
        ]);

        $file = $request->file('apk_file');
        $filename = "ePayPlus_v{$validated['version']}.apk";
        $path = $file->storeAs('ota-updates', $filename, 'public');

        $update = OtaUpdate::create([
            'version' => $validated['version'],
            'filename' => $filename,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'checksum' => md5_file($file->getRealPath()),
            'release_notes' => $validated['release_notes'] ?? null,
            'rollout_type' => $validated['rollout_type'],
            'rollout_percentage' => $validated['rollout_percentage'] ?? 100,
            'target_group_id' => $validated['target_group_id'] ?? null,
            'status' => 'draft',
        ]);

        return redirect()->route('epayplus.fleet.updates')->with('success', "OTA update v{$validated['version']} uploaded.");
    }

    public function releaseUpdate(OtaUpdate $update)
    {
        $query = Device::query();

        if ($update->rollout_type === 'group' && $update->target_group_id) {
            $query->where('group_id', $update->target_group_id);
        }

        if ($update->rollout_type === 'staged') {
            $totalDevices = $query->count();
            $targetCount = (int) ceil($totalDevices * ($update->rollout_percentage / 100));
            $devices = $query->inRandomOrder()->limit($targetCount)->get();
        } else {
            $devices = $query->get();
        }

        foreach ($devices as $device) {
            DeviceUpdateStatus::updateOrCreate(
                ['device_id' => $device->id, 'ota_update_id' => $update->id],
                ['status' => 'pending']
            );
        }

        $update->update([
            'status' => 'active',
            'pending_count' => $devices->count(),
            'released_at' => now(),
        ]);

        return back()->with('success', "Update v{$update->version} released to {$devices->count()} devices.");
    }

    public function pauseUpdate(OtaUpdate $update)
    {
        $update->update(['status' => 'paused']);
        return back()->with('success', "Update v{$update->version} paused.");
    }

    public function rollbackUpdate(OtaUpdate $update)
    {
        $update->update(['status' => 'rolled_back']);
        $update->deviceStatuses()->where('status', 'pending')->update(['status' => 'skipped']);

        return back()->with('success', "Update v{$update->version} rolled back.");
    }

    // ─── Alerts ─────────────────────────────────────────────────

    public function alerts(Request $request)
    {
        $query = DeviceAlert::with('device');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'active');
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $alerts = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        $alertStats = [
            'active' => DeviceAlert::where('status', 'active')->count(),
            'critical' => DeviceAlert::where('status', 'active')->where('severity', 'critical')->count(),
            'today' => DeviceAlert::whereDate('created_at', today())->count(),
            'resolved_today' => DeviceAlert::whereIn('status', ['resolved', 'auto_resolved'])
                ->whereDate('resolved_at', today())->count(),
        ];

        return view('epayplus.fleet.alerts', compact('alerts', 'alertStats'));
    }

    public function acknowledgeAlert(DeviceAlert $alert)
    {
        $alert->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
        ]);

        return back()->with('success', 'Alert acknowledged.');
    }

    public function resolveAlert(DeviceAlert $alert)
    {
        $alert->resolve(auth()->user()->name ?? 'admin');
        return back()->with('success', 'Alert resolved.');
    }

    public function bulkResolveAlerts(Request $request)
    {
        $validated = $request->validate([
            'alert_ids' => 'required|array',
            'alert_ids.*' => 'integer|exists:epay_device_alerts,id',
        ]);

        DeviceAlert::whereIn('id', $validated['alert_ids'])->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => auth()->user()->name ?? 'admin',
        ]);

        return back()->with('success', count($validated['alert_ids']) . ' alerts resolved.');
    }

    // ─── Groups ─────────────────────────────────────────────────

    public function groups()
    {
        $groups = DeviceGroup::withCount('devices')->get();
        return view('epayplus.fleet.groups', compact('groups'));
    }

    public function storeGroup(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:7',
        ]);

        DeviceGroup::create($validated);

        return back()->with('success', "Group '{$validated['name']}' created.");
    }

    public function updateGroup(Request $request, DeviceGroup $group)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:7',
            'is_active' => 'boolean',
        ]);

        $group->update($validated);

        return back()->with('success', 'Group updated.');
    }

    public function deleteGroup(DeviceGroup $group)
    {
        Device::where('group_id', $group->id)->update(['group_id' => null]);
        $group->delete();

        return back()->with('success', 'Group deleted.');
    }

    // ─── AJAX Endpoints ─────────────────────────────────────────

    public function liveStatus()
    {
        $devices = Device::select('id', 'device_id', 'name', 'status', 'last_seen_at', 'battery_level', 'signal_strength')
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'device_id' => $d->device_id,
                'name' => $d->name,
                'is_online' => $d->isOnline(),
                'last_seen' => $d->last_seen_at?->diffForHumans(),
                'battery' => $d->battery_level,
                'signal' => $d->signal_strength,
            ]);

        return response()->json([
            'devices' => $devices,
            'stats' => [
                'online' => $devices->where('is_online', true)->count(),
                'offline' => $devices->where('is_online', false)->count(),
                'total' => $devices->count(),
            ],
        ]);
    }

    public function commandHistory(Device $device)
    {
        $commands = $device->commands()
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($commands);
    }
}
