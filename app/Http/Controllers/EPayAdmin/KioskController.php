<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Device;
use App\Models\EPayPlus\KioskCollection;
use Illuminate\Http\Request;

class KioskController extends Controller
{
    public function index(Request $request)
    {
        $query = Device::where('type', 'kiosk');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $kiosks = $query->orderByDesc('last_seen_at')->paginate(20)->withQueryString();

        $stats = [
            'total' => Device::where('type', 'kiosk')->count(),
            'online' => Device::where('type', 'kiosk')->where('status', 'online')->count(),
            'total_collected' => KioskCollection::sum('amount'),
            'pending_collection' => 0,
        ];

        return view('epayplus.kiosks', compact('kiosks', 'stats'));
    }

    public function show(Device $device)
    {
        abort_if($device->type !== 'kiosk', 404);

        $collections = $device->collections()->latest('collected_at')->paginate(15);
        $config = $device->config ?? [];

        return view('epayplus.kiosk-detail', compact('device', 'collections', 'config'));
    }

    public function updateConfig(Request $request, Device $device)
    {
        abort_if($device->type !== 'kiosk', 404);

        $validated = $request->validate([
            'accepted_denominations' => 'nullable|array',
            'wifi_enabled' => 'nullable|boolean',
            'wifi_rate_per_minute' => 'nullable|numeric|min:0',
            'auto_print_receipt' => 'nullable|boolean',
            'idle_timeout_seconds' => 'nullable|integer|min:30',
            'max_transaction_amount' => 'nullable|numeric|min:0',
        ]);

        $config = $device->config ?? [];
        $device->config = array_merge($config, $validated);
        $device->save();

        return back()->with('success', 'Kiosk configuration updated.');
    }

    public function recordCollection(Request $request, Device $device)
    {
        abort_if($device->type !== 'kiosk', 404);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'coins_amount' => 'nullable|numeric|min:0',
            'bills_amount' => 'nullable|numeric|min:0',
            'collected_by' => 'required|string|max:150',
            'notes' => 'nullable|string|max:500',
        ]);

        $validated['device_id'] = $device->id;
        $validated['collected_at'] = now();
        $validated['period_end'] = now();

        $lastCollection = $device->collections()->latest('collected_at')->first();
        $validated['period_start'] = $lastCollection?->collected_at ?? $device->registered_at;

        KioskCollection::create($validated);

        return back()->with('success', 'Collection recorded successfully.');
    }

    public function toggleLock(Device $device)
    {
        abort_if($device->type !== 'kiosk', 404);

        $config = $device->config ?? [];
        $config['locked'] = !($config['locked'] ?? false);
        $device->config = $config;
        $device->save();

        $state = $config['locked'] ? 'locked' : 'unlocked';
        return back()->with('success', "Kiosk {$state} successfully.");
    }
}
