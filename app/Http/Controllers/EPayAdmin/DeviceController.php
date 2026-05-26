<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Device;
use App\Models\EPayPlus\DeviceCommand;
use App\Models\EPayPlus\DeviceLog;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $query = Device::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('device_id', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('group')) {
            $query->where('group_zone', $request->group);
        }

        $devices = $query->orderByDesc('last_seen_at')->paginate(20)->withQueryString();
        $groups = Device::whereNotNull('group_zone')->distinct()->pluck('group_zone');
        $stats = [
            'total' => Device::count(),
            'online' => Device::where('status', 'online')->count(),
            'offline' => Device::where('status', 'offline')->count(),
            'kiosks' => Device::where('type', 'kiosk')->count(),
        ];

        return view('epayplus.devices', compact('devices', 'groups', 'stats'));
    }

    public function show(Device $device)
    {
        $device->load(['commands' => fn($q) => $q->latest()->limit(20)]);
        $logs = $device->logs()->latest()->limit(50)->get();
        $collections = $device->collections()->latest()->limit(10)->get();

        return view('epayplus.device-detail', compact('device', 'logs', 'collections'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:100|unique:epay_devices,device_id',
            'name' => 'required|string|max:150',
            'type' => 'required|in:retailer,kiosk',
            'retailer_id' => 'nullable|integer',
            'location' => 'nullable|string|max:255',
            'group_zone' => 'nullable|string|max:100',
        ]);

        $validated['status'] = 'offline';
        $validated['registered_at'] = now();

        Device::create($validated);

        return redirect()->route('epayplus.devices')->with('success', 'Device registered successfully.');
    }

    public function update(Request $request, Device $device)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'type' => 'required|in:retailer,kiosk',
            'retailer_id' => 'nullable|integer',
            'location' => 'nullable|string|max:255',
            'group_zone' => 'nullable|string|max:100',
            'operating_hours' => 'nullable|string|max:50',
            'enabled_services' => 'nullable|array',
        ]);

        $device->update($validated);

        return redirect()->route('epayplus.devices.show', $device)->with('success', 'Device updated.');
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
            'expires_at' => now()->addHours(24),
        ]);

        return back()->with('success', "Command '{$validated['command']}' queued for {$device->name}.");
    }

    public function logs(Device $device, Request $request)
    {
        $query = $device->logs();

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        $logs = $query->latest('created_at')->paginate(50)->withQueryString();

        return view('epayplus.device-logs', compact('device', 'logs'));
    }

    public function destroyDevice(Device $device)
    {
        $device->delete();
        return redirect()->route('epayplus.devices')->with('success', 'Device removed.');
    }
}
