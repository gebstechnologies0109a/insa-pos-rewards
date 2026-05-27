<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\POS\Company;
use App\Models\POS\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(Request $request): View
    {
        $companies = Company::orderBy('name')->get();

        $query = Device::with(['branch.company'])
            ->orderBy('device_name')
            ->orderBy('device_fingerprint');

        if ($request->filled('company_id')) {
            $query->whereHas('branch', fn ($q) => $q->where('company_id', $request->integer('company_id')));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        $devices = $query->get();
        $branches = Branch::with('company')->orderBy('name')->get();

        return view('super-admin.devices.index', compact('devices', 'companies', 'branches'));
    }

    public function create(): View
    {
        $companies = Company::orderBy('name')->get();
        $branches = Branch::with('company')->orderBy('name')->get();

        return view('super-admin.devices.create', compact('companies', 'branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id'          => 'required|exists:branches,id',
            'device_name'        => 'nullable|string|max:255',
            'device_fingerprint' => 'required|string|max:128|unique:devices,device_fingerprint',
            'status'             => 'required|in:active,inactive',
        ]);

        Device::create($data);

        return redirect()->route('super-admin.devices.index')
            ->with('success', 'Device registered successfully.');
    }

    public function edit(Device $device): View
    {
        $device->load('branch.company');
        $companies = Company::orderBy('name')->get();
        $branches = Branch::with('company')->orderBy('name')->get();

        return view('super-admin.devices.edit', compact('device', 'companies', 'branches'));
    }

    public function update(Request $request, Device $device): RedirectResponse
    {
        $data = $request->validate([
            'branch_id'          => 'required|exists:branches,id',
            'device_name'        => 'nullable|string|max:255',
            'device_fingerprint' => 'required|string|max:128|unique:devices,device_fingerprint,' . $device->id,
            'status'             => 'required|in:active,inactive',
        ]);

        $device->update($data);

        return redirect()->route('super-admin.devices.index')
            ->with('success', 'Device updated successfully.');
    }
}
