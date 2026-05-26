<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Commission;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $query = Commission::query();

        if ($request->filled('provider_code')) {
            $query->where('provider_code', $request->provider_code);
        }

        if ($request->filled('tier')) {
            $query->where('tier', $request->tier);
        }

        if ($request->filled('retailer_id')) {
            $query->where('retailer_id', $request->retailer_id);
        }

        $commissions = $query->orderBy('provider_code')->orderBy('product_code')->paginate(30)->withQueryString();

        $stats = [
            'total_rules' => Commission::count(),
            'active_rules' => Commission::where('is_active', true)->count(),
            'providers_covered' => Commission::distinct('provider_code')->count('provider_code'),
            'retailer_overrides' => Commission::whereNotNull('retailer_id')->count(),
        ];

        return view('epayplus.commissions', compact('commissions', 'stats'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'retailer_id' => 'nullable|integer',
            'provider_code' => 'nullable|string|max:50',
            'product_code' => 'nullable|string|max:50',
            'rate' => 'required|numeric|min:0|max:100',
            'type' => 'required|in:percentage,fixed',
            'tier' => 'required|in:default,silver,gold,platinum',
        ]);

        Commission::create($validated);

        return back()->with('success', 'Commission rule created.');
    }

    public function update(Request $request, Commission $commission)
    {
        $validated = $request->validate([
            'retailer_id' => 'nullable|integer',
            'provider_code' => 'nullable|string|max:50',
            'product_code' => 'nullable|string|max:50',
            'rate' => 'required|numeric|min:0|max:100',
            'type' => 'required|in:percentage,fixed',
            'tier' => 'required|in:default,silver,gold,platinum',
            'is_active' => 'nullable|boolean',
        ]);

        $commission->update($validated);

        return back()->with('success', 'Commission rule updated.');
    }

    public function destroy(Commission $commission)
    {
        $commission->delete();
        return back()->with('success', 'Commission rule deleted.');
    }

    public function toggleStatus(Commission $commission)
    {
        $commission->update(['is_active' => !$commission->is_active]);
        return back()->with('success', 'Commission rule status toggled.');
    }
}
