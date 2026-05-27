<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\DeviceConfig;
use Illuminate\Http\Request;

class DeviceConfigController extends Controller
{
    public function index()
    {
        $configs = DeviceConfig::withCount('devices')->orderBy('name')->get();
        return view('epayplus.fleet.configs', compact('configs'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'eload' => 'boolean',
            'bills' => 'boolean',
            'gcash' => 'boolean',
            'maya' => 'boolean',
            'ecash' => 'boolean',
        ]);

        DeviceConfig::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'settings' => [
                'services' => [
                    'eload' => $request->boolean('eload', true),
                    'bills' => $request->boolean('bills', true),
                    'gcash' => $request->boolean('gcash', true),
                    'maya' => $request->boolean('maya', true),
                    'ecash' => $request->boolean('ecash', true),
                ],
            ],
        ]);

        return back()->with('success', 'Config profile created.');
    }

    public function update(Request $request, DeviceConfig $config)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $config->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'settings' => [
                'services' => [
                    'eload' => $request->boolean('eload'),
                    'bills' => $request->boolean('bills'),
                    'gcash' => $request->boolean('gcash'),
                    'maya' => $request->boolean('maya'),
                    'ecash' => $request->boolean('ecash'),
                ],
                'heartbeat_interval_sec' => (int) $request->input('heartbeat_interval_sec', 60),
            ],
        ]);

        return back()->with('success', 'Config profile updated.');
    }

    public function destroy(DeviceConfig $config)
    {
        if ($config->is_default) {
            return back()->with('error', 'Cannot delete the default config profile.');
        }

        $config->devices()->update(['config_profile_id' => null]);
        $config->delete();

        return back()->with('success', 'Config profile deleted.');
    }
}
