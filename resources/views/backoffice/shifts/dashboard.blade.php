@extends('layouts.backoffice')

@section('page-title', 'Shift Dashboard')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Shift Dashboard</h1>
    <div class="flex gap-2 text-sm">
        <a href="{{ route('backoffice.shifts.variance') }}" class="px-4 py-2 bg-orange-600 text-white rounded hover:bg-orange-700">Variance Report</a>
        <a href="{{ route('backoffice.shifts.audit') }}" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Audit Trail</a>
        <a href="{{ route('backoffice.shifts.dashboard', array_merge(request()->query(), ['export' => 'csv'])) }}"
           class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Export CSV</a>
        <a href="{{ route('backoffice.shifts.dashboard', array_merge(request()->query(), ['export' => 'pdf'])) }}"
           class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Export PDF</a>
    </div>
</div>

<!-- Metrics -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Total Shifts</div>
        <div class="text-2xl font-bold mt-1">{{ $metrics->total_shifts }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Open</div>
        <div class="text-2xl font-bold mt-1 text-green-600">{{ $metrics->open_shifts }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Closed</div>
        <div class="text-2xl font-bold mt-1 text-gray-600">{{ $metrics->closed_shifts }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Total Sales</div>
        <div class="text-2xl font-bold mt-1">&#8369;{{ number_format($metrics->total_sales, 2) }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Total Variance</div>
        @php $tv = $metrics->total_variance; @endphp
        <div class="text-2xl font-bold mt-1 {{ $tv == 0 ? 'text-green-600' : ($tv > 0 ? 'text-blue-600' : 'text-red-600') }}">
            {{ $tv >= 0 ? '+' : '' }}&#8369;{{ number_format($tv, 2) }}
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" action="{{ route('backoffice.shifts.dashboard') }}" class="flex flex-wrap gap-3 items-end">
        @unless(auth()->user()->isManager())
        <x-branch-selector :branch-id="$branchId" :branches="$branches" :auto-submit="false" />
        @endunless
        <div>
            <label class="block text-xs text-gray-500 mb-1">Cashier</label>
            <select name="user_id" class="p-2 border rounded text-sm">
                <option value="">All</option>
                @foreach($cashiers as $c)
                <option value="{{ $c->id }}" {{ request('user_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Status</label>
            <select name="status" class="p-2 border rounded text-sm">
                <option value="">All</option>
                <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
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
        <a href="{{ route('backoffice.shifts.dashboard') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 text-sm">Clear</a>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-center p-3 font-medium">ID</th>
                <th class="text-left p-3 font-medium">Cashier</th>
                <th class="text-left p-3 font-medium">Branch</th>
                <th class="text-left p-3 font-medium">Opened</th>
                <th class="text-left p-3 font-medium">Closed</th>
                <th class="text-right p-3 font-medium">Opening</th>
                <th class="text-right p-3 font-medium">Closing</th>
                <th class="text-right p-3 font-medium">Sales</th>
                <th class="text-right p-3 font-medium">Variance</th>
                <th class="text-center p-3 font-medium">Status</th>
                <th class="text-center p-3 font-medium">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($shifts as $shift)
            @php
                $v = $shift->cash_variance;
                $vc = $v === null ? 'text-gray-400' : ($v == 0 ? 'text-green-600' : ($v > 0 ? 'text-blue-600' : 'text-red-600'));
            @endphp
            <tr class="border-t hover:bg-gray-50">
                <td class="p-3 text-center font-mono text-xs">{{ $shift->id }}</td>
                <td class="p-3 font-medium">{{ $shift->user?->name ?? '—' }}</td>
                <td class="p-3 text-gray-600">{{ $shift->branch?->name ?? '—' }}</td>
                <td class="p-3 text-gray-600 text-xs">{{ $shift->opened_at?->format('M d, Y h:i A') }}</td>
                <td class="p-3 text-gray-600 text-xs">{{ $shift->closed_at?->format('M d, Y h:i A') ?? '—' }}</td>
                <td class="p-3 text-right font-mono">&#8369;{{ number_format($shift->opening_cash, 2) }}</td>
                <td class="p-3 text-right font-mono">{{ $shift->closing_cash !== null ? '₱' . number_format($shift->closing_cash, 2) : '—' }}</td>
                <td class="p-3 text-right font-mono">{{ $shift->system_sales_total !== null ? '₱' . number_format($shift->system_sales_total, 2) : '—' }}</td>
                <td class="p-3 text-right font-mono font-bold {{ $vc }}">
                    @if($v !== null) {{ $v >= 0 ? '+' : '' }}&#8369;{{ number_format($v, 2) }} @else — @endif
                </td>
                <td class="p-3 text-center">
                    @if($shift->status === 'open')
                    <span class="px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">Open</span>
                    @else
                    <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800">Closed</span>
                    @endif
                </td>
                <td class="p-3 text-center">
                    <div class="flex gap-1 justify-center">
                        <a href="{{ route('backoffice.shifts.show', $shift) }}" class="text-blue-600 hover:underline text-xs">View</a>
                        <a href="{{ route('backoffice.shifts.export.csv', $shift) }}" class="text-green-600 hover:underline text-xs">CSV</a>
                        <a href="{{ route('backoffice.shifts.export.pdf', $shift) }}" class="text-red-600 hover:underline text-xs">PDF</a>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="11" class="p-6 text-center text-gray-400">No shifts found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $shifts->links() }}</div>
@endsection
