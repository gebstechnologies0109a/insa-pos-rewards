@extends('layouts.backoffice')

@section('page-title', 'Shift Management')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Shift Management</h1>
    <div class="flex gap-2 text-sm">
        <a href="{{ route('backoffice.shifts.dashboard') }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Dashboard</a>
        <a href="{{ route('backoffice.shifts.audit') }}" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Audit Trail</a>
        <a href="{{ route('backoffice.shifts', array_merge(request()->query(), ['export' => 'csv'])) }}"
           class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Export CSV</a>
        <a href="{{ route('backoffice.shifts', array_merge(request()->query(), ['export' => 'pdf'])) }}"
           class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Export PDF</a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" action="{{ route('backoffice.shifts') }}" class="flex flex-wrap gap-3 items-end">
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
            <label class="block text-xs text-gray-500 mb-1">Cashier</label>
            <select name="user_id" class="p-2 border rounded text-sm">
                <option value="">All Cashiers</option>
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
        <a href="{{ route('backoffice.shifts') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 text-sm">Clear</a>
    </form>
</div>

<!-- Shifts Table -->
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-center p-3 font-medium">Shift ID</th>
                <th class="text-left p-3 font-medium">Cashier Name</th>
                <th class="text-left p-3 font-medium">Branch</th>
                <th class="text-left p-3 font-medium">Opened At</th>
                <th class="text-left p-3 font-medium">Closed At</th>
                <th class="text-right p-3 font-medium">Opening Cash</th>
                <th class="text-right p-3 font-medium">Closing Cash</th>
                <th class="text-right p-3 font-medium">System Sales</th>
                <th class="text-right p-3 font-medium">Variance</th>
                <th class="text-center p-3 font-medium">Status</th>
                <th class="text-center p-3 font-medium">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($shifts as $shift)
            @php
                $variance = $shift->cash_variance;
                $varianceClass = $variance === null ? 'text-gray-400'
                    : ($variance == 0 ? 'text-green-600'
                    : ($variance > 0 ? 'text-blue-600' : 'text-red-600'));
            @endphp
            <tr class="border-t hover:bg-gray-50">
                <td class="p-3 text-center font-mono text-xs font-bold">{{ $shift->id }}</td>
                <td class="p-3 font-medium">{{ $shift->user?->name ?? '—' }}</td>
                <td class="p-3 text-gray-600">{{ $shift->branch?->name ?? '—' }}</td>
                <td class="p-3 text-gray-600 text-xs">{{ $shift->opened_at?->format('M d, Y h:i A') }}</td>
                <td class="p-3 text-gray-600 text-xs">{{ $shift->closed_at?->format('M d, Y h:i A') ?? '—' }}</td>
                <td class="p-3 text-right font-mono">&#8369;{{ number_format($shift->opening_cash, 2) }}</td>
                <td class="p-3 text-right font-mono">{{ $shift->closing_cash !== null ? '₱' . number_format($shift->closing_cash, 2) : '—' }}</td>
                <td class="p-3 text-right font-mono">{{ $shift->system_sales_total !== null ? '₱' . number_format($shift->system_sales_total, 2) : '—' }}</td>
                <td class="p-3 text-right font-mono font-bold {{ $varianceClass }}">
                    @if($variance !== null)
                        {{ $variance >= 0 ? '+' : '' }}&#8369;{{ number_format($variance, 2) }}
                    @else
                        —
                    @endif
                </td>
                <td class="p-3 text-center">
                    @if($shift->status === 'open')
                    <span class="px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">Open</span>
                    @else
                    <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800">Closed</span>
                    @endif
                </td>
                <td class="p-3 text-center">
                    <div class="flex gap-1 justify-center flex-wrap">
                        <a href="{{ route('backoffice.shifts.show', $shift) }}" class="text-blue-600 hover:underline text-xs" title="View Details">View</a>
                        <a href="{{ route('backoffice.shifts.audit', ['search' => $shift->id]) }}" class="text-indigo-600 hover:underline text-xs" title="Audit Trail">Audit</a>
                        <a href="{{ route('backoffice.shifts.export.csv', $shift) }}" class="text-green-600 hover:underline text-xs" title="Export CSV">CSV</a>
                        <a href="{{ route('backoffice.shifts.export.pdf', $shift) }}" class="text-red-600 hover:underline text-xs" title="Export PDF">PDF</a>
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
