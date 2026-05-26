<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\AuditLog;
use App\Models\EPayPlus\Provider;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    public function index(Request $request)
    {
        $query = Provider::withCount(['products', 'products as active_products_count' => fn ($q) => $q->where('is_active', true)]);

        if ($request->type) {
            $query->where('type', $request->type);
        }

        $providers = $query->orderBy('sort_order')->orderBy('name')->get();

        return view('epayplus.providers', compact('providers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'code'       => 'required|string|max:50|unique:epay_providers,code',
            'type'       => 'required|in:ELOAD,BILLS,ECASH,WIFI,OTHER',
            'category'   => 'nullable|string|max:100',
            'sms_number' => 'nullable|string|max:20',
            'sms_format' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $provider = Provider::create($data);

        AuditLog::record(auth()->id(), 'provider_created', $provider, "Created provider: {$provider->name}");

        return back()->with('success', "Provider '{$provider->name}' created.");
    }

    public function update(Request $request, Provider $provider)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'code'       => 'required|string|max:50|unique:epay_providers,code,' . $provider->id,
            'type'       => 'required|in:ELOAD,BILLS,ECASH,WIFI,OTHER',
            'category'   => 'nullable|string|max:100',
            'sms_number' => 'nullable|string|max:20',
            'sms_format' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $provider->update($data);

        AuditLog::record(auth()->id(), 'provider_updated', $provider, "Updated provider: {$provider->name}");

        return back()->with('success', "Provider '{$provider->name}' updated.");
    }

    public function toggleStatus(Provider $provider)
    {
        $provider->update(['is_active' => !$provider->is_active]);
        $status = $provider->is_active ? 'enabled' : 'disabled';

        AuditLog::record(auth()->id(), "provider_{$status}", $provider, "{$provider->name} {$status}");

        return back()->with('success', "Provider '{$provider->name}' {$status}.");
    }
}
