@extends('layouts.backoffice')

@section('page-title', 'X-Reading Report')

@section('content')
<div class="space-y-6">

    <!-- Filters -->
    <form method="GET" class="bg-white rounded-lg shadow p-4 flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Branch</label>
            <select name="branch_id" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">All Branches</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Date</label>
            <input type="date" name="date" value="{{ request('date') }}" class="border rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Cashier ID</label>
            <input type="number" name="cashier_id" value="{{ request('cashier_id') }}" placeholder="Any" class="border rounded-lg px-3 py-2 text-sm w-28">
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Filter</button>
        <a href="{{ route('readings.x') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300">Reset</a>
        <a href="{{ route('readings.x.export.csv', request()->query()) }}" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">Export CSV</a>
    </form>

    <!-- Summary -->
    @if($readings->count())
    <div class="grid grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-blue-700">{{ $readings->total() }}</div>
            <div class="text-xs text-gray-500 mt-1">Total Readings</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-green-700">&#8369;{{ number_format($readings->sum('total_sales'), 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">Total Sales (This Page)</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-gray-700">{{ $readings->sum('transaction_count') }}</div>
            <div class="text-xs text-gray-500 mt-1">Transactions (This Page)</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-red-600">&#8369;{{ number_format($readings->sum('discount_total'), 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">Discounts (This Page)</div>
        </div>
    </div>
    @endif

    <!-- Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">Branch</th>
                    <th class="px-4 py-3">Cashier</th>
                    <th class="px-4 py-3">Generated At</th>
                    <th class="px-4 py-3 text-right">Total Sales</th>
                    <th class="px-4 py-3 text-right">Txns</th>
                    <th class="px-4 py-3 text-right">Discounts</th>
                    <th class="px-4 py-3 text-right">Voids</th>
                    <th class="px-4 py-3">Payment Breakdown</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($readings as $r)
                <tr class="hover:bg-blue-50">
                    <td class="px-4 py-3 font-mono text-gray-500">{{ $r->id }}</td>
                    <td class="px-4 py-3">{{ $r->branch->name ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $r->cashier->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $r->generated_at->format('M d, Y h:i A') }}</td>
                    <td class="px-4 py-3 text-right font-semibold">&#8369;{{ number_format($r->total_sales, 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ $r->transaction_count }}</td>
                    <td class="px-4 py-3 text-right text-red-600">&#8369;{{ number_format($r->discount_total, 2) }}</td>
                    <td class="px-4 py-3 text-right text-orange-600">&#8369;{{ number_format($r->void_total, 2) }}</td>
                    <td class="px-4 py-3">
                        @if($r->payment_breakdown)
                            <div class="flex flex-wrap gap-1">
                                @foreach($r->payment_breakdown as $method => $amount)
                                    @if($amount > 0)
                                    <span class="inline-block px-2 py-0.5 rounded text-xs {{ $method === 'cash' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                        {{ ucwords(str_replace('_', ' ', $method)) }}: &#8369;{{ number_format($amount, 2) }}
                                    </span>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        <a href="{{ route('readings.x.show', $r) }}" class="inline-block px-3 py-1 bg-blue-600 text-white rounded-lg text-xs font-medium hover:bg-blue-700">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="px-4 py-12 text-center text-gray-400">No X-readings found. Generate one from the POS cashier.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $readings->withQueryString()->links() }}
</div>
@endsection
