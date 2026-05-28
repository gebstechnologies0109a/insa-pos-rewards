@extends('layouts.backoffice')

@section('page-title', 'Daily Sales Report')

@section('content')
<h1 class="text-2xl font-bold mb-6">Daily Sales</h1>

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" action="{{ route('backoffice.reports.daily-sales') }}" class="flex flex-wrap gap-3 items-end">
        <x-branch-selector :branch-id="$branchId" :branches="$branches" :auto-submit="false" />
        <div>
            <label class="block text-xs text-gray-500 mb-1">From</label>
            <input type="date" name="date_from" value="{{ $from->toDateString() }}" class="p-2 border rounded">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">To</label>
            <input type="date" name="date_to" value="{{ $to->toDateString() }}" class="p-2 border rounded">
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded">Apply</button>
    </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Transactions</div>
        <div class="text-2xl font-bold">{{ number_format($totals['transactions']) }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Revenue</div>
        <div class="text-2xl font-bold text-green-700">&#8369;{{ number_format($totals['revenue'], 2) }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Discounts</div>
        <div class="text-2xl font-bold text-red-600">&#8369;{{ number_format($totals['discounts'], 2) }}</div>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left p-3">Date</th>
                <th class="text-right p-3">Transactions</th>
                <th class="text-right p-3">Revenue</th>
                <th class="text-right p-3">Discounts</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
            <tr class="border-t">
                <td class="p-3">{{ $row['date'] }}</td>
                <td class="p-3 text-right font-mono">{{ $row['transaction_count'] }}</td>
                <td class="p-3 text-right font-mono">&#8369;{{ number_format($row['revenue'], 2) }}</td>
                <td class="p-3 text-right font-mono text-red-600">&#8369;{{ number_format($row['discounts'], 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="p-6 text-center text-gray-400">No sales in this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
