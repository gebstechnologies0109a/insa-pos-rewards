<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Services\POS\PosSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosSettingsController extends Controller
{
    public function __construct(
        protected PosSettingsService $settings,
    ) {}

    public function index()
    {
        $rewards = $this->settings->all('rewards');
        $overrides = $this->settings->all('overrides');

        return view('pos.settings.index', compact('rewards', 'overrides'));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings'         => 'required|array',
            'settings.*.key'   => 'required|string',
            'settings.*.value' => 'required|string',
        ]);

        foreach ($validated['settings'] as $setting) {
            $this->settings->set($setting['key'], $setting['value']);
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Settings saved successfully.',
        ]);
    }

    public function apiIndex(): JsonResponse
    {
        return response()->json([
            'success'  => true,
            'settings' => array_merge(
                $this->settings->all('rewards'),
                $this->settings->all('overrides'),
            ),
        ]);
    }
}
