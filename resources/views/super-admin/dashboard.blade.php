@extends('layouts.super-admin')

@section('page-title', 'Dashboard')

@section('content')
<!-- KPI Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm font-medium text-gray-500">Total Branches</div>
                <div class="text-3xl font-bold mt-1 text-gray-900">{{ $totalBranches }}</div>
            </div>
            <div class="w-12 h-12 bg-indigo-50 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm font-medium text-gray-500">Active Licenses</div>
                <div class="text-3xl font-bold mt-1 text-gray-900">{{ $totalLicenses }}</div>
                <div class="text-xs text-gray-400 mt-1">{{ $totalSlots }} total slot(s)</div>
            </div>
            <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm font-medium text-gray-500">Active Shifts</div>
                <div class="text-3xl font-bold mt-1 text-gray-900">{{ $activeShifts }}</div>
                <div class="text-xs text-gray-400 mt-1">of {{ $totalSlots }} slot(s)</div>
            </div>
            <div class="w-12 h-12 bg-amber-50 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm font-medium text-gray-500">Revenue Today</div>
                <div class="text-3xl font-bold mt-1 text-gray-900">&#8369;{{ number_format($revenueToday, 2) }}</div>
                <div class="text-xs text-gray-400 mt-1">{{ $totalSalesToday }} transaction(s)</div>
            </div>
            <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="bg-white rounded-xl shadow-sm border p-6 mb-8">
    <h3 class="font-semibold text-lg text-gray-900 mb-4">Quick Actions</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <a href="{{ route('super-admin.licenses.index') }}" class="p-5 bg-indigo-50 rounded-xl hover:bg-indigo-100 text-center border border-indigo-100 transition">
            <div class="w-10 h-10 mx-auto mb-2 bg-indigo-100 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            </div>
            <div class="font-semibold text-indigo-900">Licenses</div>
            <div class="text-xs text-indigo-600 mt-1">Slots &amp; status</div>
        </a>
        <a href="{{ route('super-admin.branches.index') }}" class="p-5 bg-indigo-50 rounded-xl hover:bg-indigo-100 text-center border border-indigo-100 transition">
            <div class="w-10 h-10 mx-auto mb-2 bg-indigo-100 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
            <div class="font-semibold text-indigo-900">Branches</div>
            <div class="text-xs text-indigo-600 mt-1">Overview &amp; details</div>
        </a>
        <a href="{{ route('super-admin.sessions.index') }}" class="p-5 bg-indigo-50 rounded-xl hover:bg-indigo-100 text-center border border-indigo-100 transition">
            <div class="w-10 h-10 mx-auto mb-2 bg-indigo-100 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
            </div>
            <div class="font-semibold text-indigo-900">POS Sessions</div>
            <div class="text-xs text-indigo-600 mt-1">Active terminals</div>
        </a>
        <a href="{{ route('backoffice.dashboard') }}" class="p-5 bg-blue-50 rounded-xl hover:bg-blue-100 text-center border border-blue-100 transition">
            <div class="w-10 h-10 mx-auto mb-2 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
            </div>
            <div class="font-semibold text-blue-900">Back Office</div>
            <div class="text-xs text-blue-600 mt-1">Operations dashboard</div>
        </a>
    </div>
</div>

<!-- Branch Status Table -->
<div class="bg-white rounded-xl shadow-sm border">
    <div class="px-6 py-4 border-b">
        <h3 class="font-semibold text-gray-900">Branch Status Overview</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-6 py-3 font-medium text-gray-600">Branch</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">POS Slots</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">Active Shifts</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">Utilization</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">License Status</th>
                    <th class="text-right px-6 py-3 font-medium text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($branches as $branch)
                @php
                    $slots = $branch->license?->pos_slots ?? 1;
                    $open = $branch->open_shifts_count;
                    $pct = $slots > 0 ? round(($open / $slots) * 100) : 0;
                    $licenseActive = $branch->license?->isCurrentlyActive() ?? false;
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 font-medium text-gray-900">{{ $branch->name }}</td>
                    <td class="px-6 py-3 text-center">{{ $slots }}</td>
                    <td class="px-6 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $open > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                            {{ $open }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <div class="w-20 bg-gray-200 rounded-full h-2">
                                <div class="h-2 rounded-full {{ $pct >= 90 ? 'bg-red-500' : ($pct >= 60 ? 'bg-amber-500' : 'bg-green-500') }}" style="width: {{ min($pct, 100) }}%"></div>
                            </div>
                            <span class="text-xs text-gray-500">{{ $pct }}%</span>
                        </div>
                    </td>
                    <td class="px-6 py-3 text-center">
                        @if($licenseActive)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Default (1)</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-right">
                        <a href="{{ route('super-admin.branches.show', $branch) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">View Details &rarr;</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">No branches found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
