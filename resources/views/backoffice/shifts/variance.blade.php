@extends('layouts.backoffice')

@section('page-title', 'Variance Report')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Shift Variance Report</h1>
    <div class="flex gap-2 text-sm">
        <a href="{{ route('backoffice.shifts.variance', array_merge(request()->query(), ['export' => 'csv'])) }}"
           class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Export CSV</a>
        <a href="{{ route('backoffice.shifts.dashboard') }}" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">Back to Dashboard</a>
    </div>
</div>

<!-- Summary -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Total Closed Shifts</div>
        <div class="text-2xl font-bold mt-1">{{ $summary->total_shifts }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Net Variance</div>
        @php $nv = $summary->total_variance; @endphp
        <div class="text-2xl font-bold mt-1 {{ $nv == 0 ? 'text-green-600' : ($nv > 0 ? 'text-blue-600' : 'text-red-600') }}">
            {{ $nv >= 0 ? '+' : '' }}&#8369;{{ number_format($nv, 2) }}
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Exact</div>
        <div class="text-2xl font-bold mt-1 text-green-600">{{ $summary->exact_count }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Over</div>
        <div class="text-2xl font-bold mt-1 text-blue-600">{{ $summary->over_count }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Short</div>
        <div class="text-2xl font-bold mt-1 text-red-600">{{ $summary->short_count }}</div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" action="{{ route('backoffice.shifts.variance') }}" class="flex flex-wrap gap-3 items-end">
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
            <label class="block text-xs text-gray-500 mb-1">Variance</label>
            <select name="variance_type" class="p-2 border rounded text-sm">
                <option value="">All</option>
                <option value="short" {{ request('variance_type') === 'short' ? 'selected' : '' }}>Short (negative)</option>
                <option value="over" {{ request('variance_type') === 'over' ? 'selected' : '' }}>Over (positive)</option>
                <option value="exact" {{ request('variance_type') === 'exact' ? 'selected' : '' }}>Exact (zero)</option>
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
        <a href="{{ route('backoffice.shifts.variance') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 text-sm">Clear</a>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left p-3 font-medium">Cashier</th>
                <th class="text-left p-3 font-medium">Branch</th>
                <th class="text-left p-3 font-medium">Closed At</th>
                <th class="text-right p-3 font-medium">Opening Cash</th>
                <th class="text-right p-3 font-medium">System Sales</th>
                <th class="text-right p-3 font-medium">Expected Cash</th>
                <th class="text-right p-3 font-medium">Closing Cash</th>
                <th class="text-right p-3 font-medium">Variance</th>
                <th class="text-center p-3 font-medium">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($shifts as $shift)
            @php
                $expected = $shift->opening_cash + $shift->system_sales_total;
                $v = $shift->cash_variance;
                $rowBg = $v == 0 ? '' : ($v > 0 ? 'bg-blue-50' : 'bg-red-50');
                $vc = $v == 0 ? 'text-green-600' : ($v > 0 ? 'text-blue-600' : 'text-red-600');
            @endphp
            <tr class="border-t {{ $rowBg }} hover:bg-gray-50">
                <td class="p-3 font-medium">{{ $shift->user?->name ?? '—' }}</td>
                <td class="p-3 text-gray-600">{{ $shift->branch?->name ?? '—' }}</td>
                <td class="p-3 text-gray-600 text-xs">{{ $shift->closed_at?->format('M d, Y h:i A') }}</td>
                <td class="p-3 text-right font-mono">&#8369;{{ number_format($shift->opening_cash, 2) }}</td>
                <td class="p-3 text-right font-mono">&#8369;{{ number_format($shift->system_sales_total, 2) }}</td>
                <td class="p-3 text-right font-mono font-bold">&#8369;{{ number_format($expected, 2) }}</td>
                <td class="p-3 text-right font-mono">&#8369;{{ number_format($shift->closing_cash, 2) }}</td>
                <td class="p-3 text-right font-mono font-bold {{ $vc }}">
                    {{ $v >= 0 ? '+' : '' }}&#8369;{{ number_format($v, 2) }}
                </td>
                <td class="p-3 text-center">
                    @if($v == 0)
                    <span class="px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">OK</span>
                    @elseif($v > 0)
                    <span class="px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">Over</span>
                    @else
                    <span class="px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800">Short</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="9" class="p-6 text-center text-gray-400">No closed shifts found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $shifts->links() }}</div>
@endsection
