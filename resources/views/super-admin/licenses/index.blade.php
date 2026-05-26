@extends('layouts.super-admin')

@section('page-title', 'License Management')

@section('content')
<!-- Add License Form -->
<div class="bg-white rounded-xl shadow-sm border mb-6">
    <div class="px-6 py-4 border-b">
        <h3 class="font-semibold text-gray-900">Assign New License</h3>
    </div>
    <form method="POST" action="{{ route('super-admin.licenses.store') }}" class="px-6 py-4">
        @csrf
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Branch</label>
                <select name="branch_id" required class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Select branch...</option>
                    @foreach($branches->where('license', null) as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">POS Slots</label>
                <input type="number" name="pos_slots" value="1" min="1" max="100" required class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-24 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                Create License
            </button>
        </div>
    </form>
</div>

<!-- License Table -->
<div class="bg-white rounded-xl shadow-sm border">
    <div class="px-6 py-4 border-b">
        <h3 class="font-semibold text-gray-900">All Branch Licenses</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-6 py-3 font-medium text-gray-600">Branch</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">POS Slots</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">Active Shifts</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">Status</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($branches as $branch)
                @php
                    $license = $branch->license;
                    $slots = $license?->pos_slots ?? 1;
                    $isActive = $license?->active ?? false;
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 font-medium text-gray-900">{{ $branch->name }}</td>
                    <td class="px-6 py-3 text-center">
                        <span class="font-semibold">{{ $slots }}</span>
                    </td>
                    <td class="px-6 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $branch->open_shifts_count > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                            {{ $branch->open_shifts_count }} / {{ $slots }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-center">
                        @if($isActive)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                        @elseif($license)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Inactive</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">No License</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-center">
                        <form method="POST" action="{{ route('super-admin.licenses.update', $branch) }}" class="inline-flex items-center gap-2">
                            @csrf
                            @method('PUT')
                            <input type="number" name="pos_slots" value="{{ $slots }}" min="1" max="100" class="border border-gray-300 rounded px-2 py-1 text-sm w-16 text-center focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <select name="active" class="border border-gray-300 rounded px-2 py-1 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="1" {{ $isActive ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ !$isActive ? 'selected' : '' }}>Inactive</option>
                            </select>
                            <button type="submit" class="bg-indigo-600 text-white px-3 py-1 rounded text-xs font-medium hover:bg-indigo-700 transition">
                                Update
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
