@extends('layouts.owner')

@section('content')
<h1 class="text-2xl font-bold text-slate-800 mb-6">Owner Dashboard</h1>

<div class="mb-6">
    <form method="GET" action="{{ route('owner.dashboard') }}" class="inline-flex">
        <x-branch-selector :branch-id="$branchId" :branches="$branches" />
    </form>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-lg shadow p-5 border-l-4 border-green-500">
        <div class="text-sm text-gray-500">Revenue Today</div>
        <div class="text-2xl font-bold text-green-700">&#8369;{{ number_format($revenueToday, 2) }}</div>
        <div class="text-xs text-gray-400 mt-1">{{ $salesToday }} sales</div>
    </div>
    <div class="bg-white rounded-lg shadow p-5 border-l-4 border-blue-500">
        <div class="text-sm text-gray-500">Revenue (MTD)</div>
        <div class="text-2xl font-bold text-blue-700">&#8369;{{ number_format($revenueMonth, 2) }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-5 border-l-4 border-amber-500">
        <div class="text-sm text-gray-500">Open Shifts</div>
        <div class="text-2xl font-bold">{{ $openShifts }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-5 border-l-4 border-red-500">
        <div class="text-sm text-gray-500">Low Stock SKUs</div>
        <div class="text-2xl font-bold text-red-600">{{ $lowStockCount }}</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="font-semibold text-lg mb-4">Last 7 Days</h2>
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left p-2">Date</th>
                    <th class="text-right p-2">Txns</th>
                    <th class="text-right p-2">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dailySales as $day)
                <tr class="border-t">
                    <td class="p-2">{{ $day['date'] }}</td>
                    <td class="p-2 text-right">{{ $day['transaction_count'] }}</td>
                    <td class="p-2 text-right">&#8369;{{ number_format($day['revenue'], 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="p-4 text-center text-gray-400">No sales this week.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="font-semibold text-lg mb-4">Top Products (30d)</h2>
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left p-2">Product</th>
                    <th class="text-right p-2">Qty</th>
                    <th class="text-right p-2">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @forelse($topProducts as $p)
                <tr class="border-t">
                    <td class="p-2">{{ $p['name'] }}</td>
                    <td class="p-2 text-right">{{ number_format($p['qty_sold'], 0) }}</td>
                    <td class="p-2 text-right">&#8369;{{ number_format($p['revenue'], 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="p-4 text-center text-gray-400">No data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-6 grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
    <div class="bg-white rounded-lg shadow p-4">Active products: <strong>{{ $activeProducts }}</strong></div>
    <div class="bg-white rounded-lg shadow p-4">Staff at branch: <strong>{{ $staffCount }}</strong></div>
    <div class="bg-white rounded-lg shadow p-4">
        <a href="{{ route('backoffice.reports.daily-sales', ['branch_id' => $branchId]) }}" class="text-blue-600 hover:underline">Full reports →</a>
    </div>
</div>
@endsection
