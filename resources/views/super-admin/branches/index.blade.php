@extends('layouts.super-admin')

@section('page-title', 'Branch Overview')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h3 class="font-semibold text-gray-900 text-lg">Branches</h3>
        <p class="text-sm text-gray-500 mt-1">Company → Branch hierarchy for POS operations.</p>
    </div>
    <a href="{{ route('super-admin.branches.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
        Add Branch
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-6 py-3 font-medium text-gray-600">Company</th>
                    <th class="text-left px-6 py-3 font-medium text-gray-600">Branch</th>
                    <th class="text-left px-6 py-3 font-medium text-gray-600">Address</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">Users</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">Devices</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">POS Slots</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">Open Shifts</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">License</th>
                    <th class="text-right px-6 py-3 font-medium text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($branches as $branch)
                @php
                    $slots = $branch->license?->pos_slots ?? 1;
                    $licenseActive = $branch->license?->isCurrentlyActive() ?? false;
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 text-gray-600">{{ $branch->company?->name ?? '—' }}</td>
                    <td class="px-6 py-3 font-medium text-gray-900">{{ $branch->name }}</td>
                    <td class="px-6 py-3 text-gray-600">{{ $branch->address ?? '—' }}</td>
                    <td class="px-6 py-3 text-center">{{ $branch->users_count }}</td>
                    <td class="px-6 py-3 text-center">{{ $branch->devices_count }}</td>
                    <td class="px-6 py-3 text-center">{{ $slots }}</td>
                    <td class="px-6 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $branch->open_shifts_count > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                            {{ $branch->open_shifts_count }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-center">
                        @if($licenseActive)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Default</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-right space-x-2">
                        <a href="{{ route('super-admin.branches.show', $branch) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Details</a>
                        <a href="{{ route('super-admin.branches.edit', $branch) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Edit</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-6 py-8 text-center text-gray-500">No branches found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
