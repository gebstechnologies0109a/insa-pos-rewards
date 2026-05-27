@extends('layouts.backoffice')

@section('page-title', 'Expiry Alerts')

@section('content')
<h1 class="text-2xl font-bold mb-6">Expiry Dashboard</h1>

@if(session('success'))
<div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">30-day warnings</div>
        <div class="text-2xl font-bold text-yellow-600">{{ $counts['thirty_day'] }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">7-day warnings</div>
        <div class="text-2xl font-bold text-orange-600">{{ $counts['seven_day'] }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Expired</div>
        <div class="text-2xl font-bold text-red-600">{{ $counts['expired'] }}</div>
    </div>
</div>

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <x-branch-selector :branch-id="$branchId" :branches="$branches" :auto-submit="false" />
        <div>
            <label class="block text-xs text-gray-500 mb-1">Bucket</label>
            <select name="bucket" class="p-2 border rounded">
                <option value="active" {{ $filter === 'active' ? 'selected' : '' }}>Active</option>
                <option value="snoozed" {{ $filter === 'snoozed' ? 'selected' : '' }}>Snoozed</option>
                <option value="handled" {{ $filter === 'handled' ? 'selected' : '' }}>Handled</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Alert type</label>
            <select name="alert_type" class="p-2 border rounded">
                <option value="">All</option>
                <option value="thirty_day" {{ request('alert_type') === 'thirty_day' ? 'selected' : '' }}>30-day</option>
                <option value="seven_day" {{ request('alert_type') === 'seven_day' ? 'selected' : '' }}>7-day</option>
                <option value="expired" {{ request('alert_type') === 'expired' ? 'selected' : '' }}>Expired</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded">Filter</button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left p-3">Product</th>
                <th class="text-left p-3">Type</th>
                <th class="text-left p-3">Expiry</th>
                <th class="text-right p-3">Batch qty</th>
                <th class="text-center p-3">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($alerts as $alert)
            <tr class="border-t">
                <td class="p-3">{{ $alert->product->name ?? '—' }}</td>
                <td class="p-3">{{ str_replace('_', ' ', $alert->alert_type) }}</td>
                <td class="p-3">{{ $alert->expiry_date?->format('Y-m-d') }}</td>
                <td class="p-3 text-right font-mono">{{ $alert->quantity }}</td>
                <td class="p-3 text-center space-x-2">
                    @if(!$alert->handled_at)
                    <form method="POST" action="{{ route('backoffice.inventory.expiry.handle', $alert) }}" class="inline">
                        @csrf
                        <button type="submit" class="text-green-600 hover:underline text-xs">Handled</button>
                    </form>
                    <form method="POST" action="{{ route('backoffice.inventory.expiry.snooze', $alert) }}" class="inline flex items-center gap-1">
                        @csrf
                        <input type="hidden" name="days" value="7">
                        <button type="submit" class="text-amber-600 hover:underline text-xs">Snooze 7d</button>
                    </form>
                    @else
                    <span class="text-gray-400 text-xs">Handled {{ $alert->handled_at->format('Y-m-d') }}</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="p-6 text-center text-gray-400">No alerts.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $alerts->links() }}</div>
@endsection
