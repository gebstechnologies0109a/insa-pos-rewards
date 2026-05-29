@extends('layouts.backoffice')

@section('page-title', 'Product Performance')

@section('content')
<h1 class="text-2xl font-bold mb-6">Product Performance</h1>

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" action="{{ route('backoffice.reports.product-performance') }}" class="flex flex-wrap gap-3 items-end">
        <x-branch-selector :branch-id="$branchId" :branches="$branches" :auto-submit="false" />
        <div>
            <label class="block text-xs text-gray-500 mb-1">From</label>
            <input type="date" name="date_from" value="{{ $from->toDateString() }}" class="p-2 border rounded">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">To</label>
            <input type="date" name="date_to" value="{{ $to->toDateString() }}" class="p-2 border rounded">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Top N</label>
            <input type="number" name="limit" value="{{ $limit }}" min="10" max="200" class="p-2 border rounded w-20">
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded">Apply</button>
    </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Units sold</div>
        <div class="text-2xl font-bold">{{ number_format($totals['qty_sold'], 2) }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Revenue (listed products)</div>
        <div class="text-2xl font-bold text-blue-700">&#8369;{{ number_format($totals['revenue'], 2) }}</div>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left p-3">Product</th>
                <th class="text-left p-3">SKU</th>
                <th class="text-right p-3">Qty sold</th>
                <th class="text-right p-3">Revenue</th>
                <th class="text-right p-3">Avg price</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
            <tr class="border-t">
                <td class="p-3">{{ $row['name'] }}</td>
                <td class="p-3 font-mono text-xs text-gray-500">{{ $row['sku'] ?? '—' }}</td>
                <td class="p-3 text-right font-mono">{{ number_format($row['qty_sold'], 2) }}</td>
                <td class="p-3 text-right font-mono">&#8369;{{ number_format($row['revenue'], 2) }}</td>
                <td class="p-3 text-right font-mono">&#8369;{{ number_format($row['avg_price'], 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="p-6 text-center text-gray-400">No product sales in this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
