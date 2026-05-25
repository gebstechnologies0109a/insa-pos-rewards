@extends('layouts.backoffice')

@section('page-title', 'Shift #' . $shift->id)

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Shift #{{ $shift->id }}</h1>
    <div class="flex gap-2 text-sm">
        <a href="{{ route('backoffice.shifts.export.csv', $shift) }}" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Export CSV</a>
        <a href="{{ route('backoffice.shifts.export.pdf', $shift) }}" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Export PDF</a>
        <a href="{{ route('backoffice.shifts.audit', ['search' => $shift->id]) }}" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">View Audit Trail</a>
        <a href="{{ route('backoffice.shifts.dashboard') }}" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">Back</a>
    </div>
</div>

<!-- Shift Info -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="font-semibold text-lg mb-4">Shift Details</h3>
        <dl class="grid grid-cols-2 gap-y-3 text-sm">
            <dt class="text-gray-500">Status</dt>
            <dd>
                @if($shift->status === 'open')
                <span class="px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">Open</span>
                @else
                <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800">Closed</span>
                @endif
            </dd>
            <dt class="text-gray-500">Cashier</dt>
            <dd class="font-medium">{{ $shift->user?->name ?? '—' }}</dd>
            <dt class="text-gray-500">Branch</dt>
            <dd>{{ $shift->branch?->name ?? '—' }}</dd>
            <dt class="text-gray-500">Opened At</dt>
            <dd>{{ $shift->opened_at?->format('M d, Y h:i:s A') }}</dd>
            <dt class="text-gray-500">Closed At</dt>
            <dd>{{ $shift->closed_at?->format('M d, Y h:i:s A') ?? '—' }}</dd>
            @if($shift->closed_at && $shift->opened_at)
            <dt class="text-gray-500">Duration</dt>
            <dd>{{ $shift->opened_at->diffForHumans($shift->closed_at, ['syntax' => true]) }}</dd>
            @endif
        </dl>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="font-semibold text-lg mb-4">Cash Summary</h3>
        <dl class="grid grid-cols-2 gap-y-3 text-sm">
            <dt class="text-gray-500">Opening Cash</dt>
            <dd class="font-mono font-bold">&#8369;{{ number_format($shift->opening_cash, 2) }}</dd>
            <dt class="text-gray-500">System Sales</dt>
            <dd class="font-mono font-bold">{{ $shift->system_sales_total !== null ? '₱' . number_format($shift->system_sales_total, 2) : '—' }}</dd>
            @php $expected = $shift->opening_cash + ($shift->system_sales_total ?? 0); @endphp
            <dt class="text-gray-500">Expected Cash</dt>
            <dd class="font-mono font-bold">{{ $shift->status === 'closed' ? '₱' . number_format($expected, 2) : '—' }}</dd>
            <dt class="text-gray-500">Closing Cash</dt>
            <dd class="font-mono font-bold">{{ $shift->closing_cash !== null ? '₱' . number_format($shift->closing_cash, 2) : '—' }}</dd>
            <dt class="text-gray-500">Variance</dt>
            @php $v = $shift->cash_variance; @endphp
            <dd class="font-mono font-bold {{ $v === null ? 'text-gray-400' : ($v == 0 ? 'text-green-600' : ($v > 0 ? 'text-blue-600' : 'text-red-600')) }}">
                @if($v !== null) {{ $v >= 0 ? '+' : '' }}&#8369;{{ number_format($v, 2) }} @else — @endif
            </dd>
        </dl>
    </div>
</div>

<!-- Sales During Shift -->
<div class="bg-white rounded-lg shadow p-6 mb-8">
    <h3 class="font-semibold text-lg mb-4">Sales During This Shift ({{ $shift->sales->count() }})</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left p-3 font-medium">Sale #</th>
                    <th class="text-right p-3 font-medium">Subtotal</th>
                    <th class="text-right p-3 font-medium">Discount</th>
                    <th class="text-right p-3 font-medium">Total</th>
                    <th class="text-center p-3 font-medium">Payment</th>
                    <th class="text-right p-3 font-medium">Tendered</th>
                    <th class="text-right p-3 font-medium">Change</th>
                    <th class="text-left p-3 font-medium">Time</th>
                </tr>
            </thead>
            <tbody>
                @forelse($shift->sales as $sale)
                <tr class="border-t hover:bg-gray-50">
                    <td class="p-3 font-mono text-xs">{{ $sale->sale_number }}</td>
                    <td class="p-3 text-right font-mono">&#8369;{{ number_format($sale->subtotal, 2) }}</td>
                    <td class="p-3 text-right font-mono">&#8369;{{ number_format($sale->discount_total, 2) }}</td>
                    <td class="p-3 text-right font-mono font-bold">&#8369;{{ number_format($sale->total, 2) }}</td>
                    <td class="p-3 text-center">
                        <span class="px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">{{ ucfirst($sale->payment_method) }}</span>
                    </td>
                    <td class="p-3 text-right font-mono">&#8369;{{ number_format($sale->amount_tendered, 2) }}</td>
                    <td class="p-3 text-right font-mono">&#8369;{{ number_format($sale->change_due, 2) }}</td>
                    <td class="p-3 text-gray-600 text-xs">{{ $sale->sold_at?->format('h:i:s A') }}</td>
                </tr>
                @empty
                <tr><td colspan="8" class="p-6 text-center text-gray-400">No sales recorded during this shift.</td></tr>
                @endforelse
            </tbody>
            @if($shift->sales->isNotEmpty())
            <tfoot class="bg-gray-50 font-bold">
                <tr>
                    <td class="p-3">Total</td>
                    <td class="p-3 text-right font-mono">&#8369;{{ number_format($shift->sales->sum('subtotal'), 2) }}</td>
                    <td class="p-3 text-right font-mono">&#8369;{{ number_format($shift->sales->sum('discount_total'), 2) }}</td>
                    <td class="p-3 text-right font-mono">&#8369;{{ number_format($shift->sales->sum('total'), 2) }}</td>
                    <td colspan="4"></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

<!-- Audit Logs -->
@if($shift->audits->isNotEmpty())
<div class="bg-white rounded-lg shadow p-6">
    <h3 class="font-semibold text-lg mb-4">Audit History</h3>
    @foreach($shift->audits->sortByDesc('created_at') as $audit)
    <div class="mb-4 border-b pb-4 last:border-0 last:pb-0">
        <div class="flex items-center gap-3 mb-2">
            @php
                $badge = match($audit->action) {
                    'open' => 'bg-green-100 text-green-800',
                    'close' => 'bg-gray-100 text-gray-800',
                    default => 'bg-yellow-100 text-yellow-800',
                };
            @endphp
            <span class="px-2 py-1 rounded text-xs font-medium {{ $badge }}">{{ ucfirst($audit->action) }}</span>
            <span class="text-sm text-gray-600">{{ $audit->user?->name }}</span>
            <span class="text-xs text-gray-400">{{ $audit->created_at?->format('M d, Y h:i:s A') }}</span>
        </div>
        @if(!empty($audit->details))
        <x-json-viewer :data="$audit->details" :id="'audit-' . $audit->id" />
        @endif
    </div>
    @endforeach
</div>
@endif
@endsection
