@extends('layouts.super-admin')

@section('page-title', 'Edit Device')

@section('content')
<div class="max-w-xl">
    <div class="mb-6">
        <a href="{{ route('super-admin.devices.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Back to devices</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h3 class="font-semibold text-gray-900 mb-4">Edit Device</h3>
        <form method="POST" action="{{ route('super-admin.devices.update', $device) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Branch</label>
                <select name="branch_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id', $device->branch_id) == $branch->id)>{{ $branch->company?->name }} — {{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Device Name (optional)</label>
                <input type="text" name="device_name" value="{{ old('device_name', $device->device_name) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Device Fingerprint</label>
                <input type="text" name="device_fingerprint" value="{{ old('device_fingerprint', $device->device_fingerprint) }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('device_fingerprint')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                <select name="status" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="active" @selected(old('status', $device->status) === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $device->status) === 'inactive')>Inactive</option>
                </select>
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">Save Changes</button>
        </form>
    </div>
</div>
@endsection
