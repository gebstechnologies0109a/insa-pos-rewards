@extends('layouts.backoffice')

@section('page-title', 'Shift Audit Trail')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Shift Audit Trail</h1>
    <div class="flex gap-2 text-sm">
        <button onclick="downloadAuditJson()" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Download JSON</button>
        <a href="{{ route('backoffice.shifts.dashboard') }}" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">Back to Dashboard</a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" action="{{ route('backoffice.shifts.audit') }}" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Shift ID or cashier name..."
                   class="p-2 border rounded text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
        </div>
        @unless(auth()->user()->isManager())
        <div>
            <label class="block text-xs text-gray-500 mb-1">Branch</label>
            <select name="branch_id" class="p-2 border rounded text-sm">
                <option value="">All Branches</option>
                @foreach($branches as $branch)
                <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        @endunless
        <div>
            <label class="block text-xs text-gray-500 mb-1">User</label>
            <select name="user_id" class="p-2 border rounded text-sm">
                <option value="">All</option>
                @foreach($users as $u)
                <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Action</label>
            <select name="action" class="p-2 border rounded text-sm">
                <option value="">All</option>
                <option value="open" {{ request('action') === 'open' ? 'selected' : '' }}>Open</option>
                <option value="close" {{ request('action') === 'close' ? 'selected' : '' }}>Close</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">From</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="p-2 border rounded text-sm">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">To</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="p-2 border rounded text-sm">
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded hover:bg-gray-800 text-sm">Filter</button>
        <a href="{{ route('backoffice.shifts.audit') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 text-sm">Clear</a>
    </form>
</div>

<!-- Audit Entries -->
<div class="space-y-4" x-data>
    @forelse($audits as $audit)
    @php
        $actionBadge = match($audit->action) {
            'open'  => 'bg-green-100 text-green-800',
            'close' => 'bg-gray-100 text-gray-800',
            default => 'bg-yellow-100 text-yellow-800',
        };
        $details = $audit->details ?? [];
    @endphp
    <div class="bg-white rounded-lg shadow overflow-hidden" x-data="{ open: false }">
        <div class="p-4 flex items-center justify-between cursor-pointer hover:bg-gray-50" @click="open = !open">
            <div class="flex items-center gap-4">
                <span class="px-2 py-1 rounded text-xs font-medium {{ $actionBadge }}">{{ ucfirst($audit->action) }}</span>
                <span class="font-medium text-sm">{{ $audit->user?->name ?? '—' }}</span>
                <span class="text-xs text-gray-400">Shift #{{ $audit->shift_id }}</span>
                <span class="text-xs text-gray-400">{{ $audit->shift?->branch?->name ?? '' }}</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-500">{{ $audit->created_at?->format('M d, Y h:i:s A') }}</span>
                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </div>
        <div x-show="open" x-cloak class="border-t p-4 bg-gray-50">
            @if(!empty($details))
            <x-json-viewer :data="$details" :id="'audit-' . $audit->id" />
            @else
            <p class="text-sm text-gray-400">No details recorded.</p>
            @endif
        </div>
    </div>
    @empty
    <div class="bg-white rounded-lg shadow p-8 text-center text-gray-400">No audit logs found.</div>
    @endforelse
</div>

<div class="mt-4">{{ $audits->links() }}</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<style>[x-cloak] { display: none !important; }</style>

<script>
    var auditJsonData = @json($auditsJson);

    function downloadAuditJson() {
        const blob = new Blob([JSON.stringify(auditJsonData, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'shift-audit-' + new Date().toISOString().slice(0, 10) + '.json';
        a.click();
        URL.revokeObjectURL(url);
    }
</script>
@endsection
