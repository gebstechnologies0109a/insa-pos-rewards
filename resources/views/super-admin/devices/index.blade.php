@extends('layouts.super-admin')

@section('page-title', 'Devices')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h3 class="font-semibold text-gray-900 text-lg">POS Devices</h3>
        <p class="text-sm text-gray-500 mt-1">Registered terminals linked to branches.</p>
    </div>
    <a href="{{ route('super-admin.devices.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
        Register Device
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border mb-6 p-4">
    <form method="GET" action="{{ route('super-admin.devices.index') }}" class="flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Company</label>
            <select name="company_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">All companies</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" @selected(request('company_id') == $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Branch</label>
            <select name="branch_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">All branches</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->company?->name }} — {{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-200 transition">Filter</button>
        @if(request()->hasAny(['company_id', 'branch_id']))
            <a href="{{ route('super-admin.devices.index') }}" class="text-sm text-indigo-600 hover:underline">Clear</a>
        @endif
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-6 py-3 font-medium text-gray-600">Device</th>
                    <th class="text-left px-6 py-3 font-medium text-gray-600">Company</th>
                    <th class="text-left px-6 py-3 font-medium text-gray-600">Branch</th>
                    <th class="text-left px-6 py-3 font-medium text-gray-600">Fingerprint</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">Status</th>
                    <th class="text-right px-6 py-3 font-medium text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($devices as $device)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 font-medium text-gray-900">{{ $device->device_name ?? 'Unnamed device' }}</td>
                    <td class="px-6 py-3 text-gray-600">{{ $device->branch?->company?->name ?? '—' }}</td>
                    <td class="px-6 py-3 text-gray-600">{{ $device->branch?->name ?? '—' }}</td>
                    <td class="px-6 py-3 font-mono text-xs text-gray-500">{{ Str::limit($device->device_fingerprint, 32) }}</td>
                    <td class="px-6 py-3 text-center">
                        @if($device->isActive())
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Inactive</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-right">
                        <a href="{{ route('super-admin.devices.edit', $device) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Edit</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">No devices found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
