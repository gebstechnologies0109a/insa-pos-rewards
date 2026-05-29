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
        $printer = $this->settings->all('printer');
        $customerDisplay = $this->settings->all('customer_display');

        return view('pos.settings.index', compact('rewards', 'overrides', 'printer', 'customerDisplay'));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings'         => 'required|array',
            'settings.*.key'   => 'required|string',
            'settings.*.value' => 'required|string',
        ]);

        foreach ($validated['settings'] as $setting) {
            $key = $setting['key'];
            $value = $setting['value'];
            if ($key === 'printer_paper_size' && ! in_array($value, ['57mm', '87mm'], true)) {
                continue;
            }
            if ($key === 'printer_font_mode' && ! in_array($value, ['fine_print', 'paper_size'], true)) {
                continue;
            }
            $this->settings->set($key, $value);
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Settings saved successfully.',
        ]);
    }

    public function apiIndex(): JsonResponse
    {
        $branchId = (int) (auth()->user()->branch_id ?? request()->integer('branch_id', 0));

        return response()->json([
            'success'  => true,
            'settings' => $branchId > 0
                ? $this->settings->syncMapForBranch($branchId)
                : array_merge(
                    $this->settings->all('rewards'),
                    $this->settings->all('overrides'),
                    $this->settings->all('receipt'),
                ),
        ]);
    }
}
