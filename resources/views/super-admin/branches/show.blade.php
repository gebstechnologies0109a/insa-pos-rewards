@extends('layouts.super-admin')

@section('page-title', $branch->name . ' — Branch Details')

@section('content')
<!-- Back Link -->
<div class="mb-4">
    <a href="{{ route('super-admin.branches.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">&larr; Back to Branches</a>
</div>

<div class="bg-white rounded-xl shadow-sm border p-5 mb-6">
    <div class="text-sm text-gray-500">Company</div>
    <div class="text-lg font-semibold text-gray-900">{{ $branch->company?->name ?? '—' }}</div>
    <div class="text-sm text-gray-600 mt-1">{{ $branch->address ?? 'No address on file' }}</div>
</div>

<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="text-sm font-medium text-gray-500">POS Slots</div>
        <div class="text-2xl font-bold mt-1">{{ $branch->license?->pos_slots ?? 1 }}</div>
        <div class="text-xs text-gray-400 mt-1">{{ ($branch->license && $branch->license->isCurrentlyActive()) ? 'Licensed' : 'Inactive / default' }}</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="text-sm font-medium text-gray-500">Open Shifts</div>
        <div class="text-2xl font-bold mt-1">{{ $openShiftCount }}</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="text-sm font-medium text-gray-500">Sales Today</div>
        <div class="text-2xl font-bold mt-1">{{ $totalSalesToday }}</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="text-sm font-medium text-gray-500">Revenue Today</div>
        <div class="text-2xl font-bold mt-1">&#8369;{{ number_format($revenueToday, 2) }}</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Users -->
    <div class="bg-white rounded-xl shadow-sm border lg:col-span-1">
        <div class="px-6 py-4 border-b">
            <h3 class="font-semibold text-gray-900">Users ({{ $branch->users->count() }})</h3>
        </div>
        <div class="divide-y max-h-72 overflow-y-auto">
            @forelse($branch->users as $user)
            <div class="px-6 py-3 flex items-center justify-between">
                <div>
                    <div class="font-medium text-gray-900 text-sm">{{ $user->name }}</div>
                    <div class="text-xs text-gray-500">{{ $user->email }}</div>
                </div>
                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">{{ ucfirst($user->role) }}</span>
            </div>
            @empty
            <div class="px-6 py-4 text-sm text-gray-500">No users assigned.</div>
            @endforelse
        </div>
    </div>

    <!-- Recent Shifts -->
    <div class="bg-white rounded-xl shadow-sm border lg:col-span-1">
        <div class="px-6 py-4 border-b">
            <h3 class="font-semibold text-gray-900">Recent Shifts</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-4 py-2 font-medium text-gray-600">User</th>
                        <th class="text-left px-4 py-2 font-medium text-gray-600">Opened</th>
                        <th class="text-center px-4 py-2 font-medium text-gray-600">Status</th>
                        <th class="text-right px-4 py-2 font-medium text-gray-600">Sales</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($recentShifts as $shift)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-gray-900">{{ $shift->user?->name ?? 'N/A' }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ $shift->opened_at?->format('M d, h:i A') }}</td>
                        <td class="px-4 py-2 text-center">
                            @if($shift->status === 'open')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Open</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Closed</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right text-gray-600">&#8369;{{ number_format($shift->system_sales_total ?? 0, 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-4 text-center text-gray-500">No shifts recorded.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Devices -->
    <div class="bg-white rounded-xl shadow-sm border lg:col-span-1">
        <div class="px-6 py-4 border-b flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Devices ({{ $branch->devices->count() }})</h3>
            <a href="{{ route('super-admin.devices.create') }}" class="text-xs text-indigo-600 hover:underline">Add</a>
        </div>
        <div class="divide-y max-h-72 overflow-y-auto">
            @forelse($branch->devices as $device)
            <div class="px-6 py-3">
                <div class="font-medium text-gray-900 text-sm">{{ $device->device_name ?? 'Unnamed device' }}</div>
                <div class="text-xs font-mono text-gray-500 mt-1">{{ Str::limit($device->device_fingerprint, 28) }}</div>
            </div>
            @empty
            <div class="px-6 py-4 text-sm text-gray-500">No devices registered.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
