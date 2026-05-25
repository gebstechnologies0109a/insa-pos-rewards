@extends('layouts.backoffice')

@section('page-title', 'Z-Reading Report (BIR Compliance)')

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
            <label class="block text-xs font-medium text-gray-500 mb-1">Z-Count #</label>
            <input type="number" name="z_count" value="{{ request('z_count') }}" placeholder="Any" class="border rounded-lg px-3 py-2 text-sm w-28">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Cashier ID</label>
            <input type="number" name="cashier_id" value="{{ request('cashier_id') }}" placeholder="Any" class="border rounded-lg px-3 py-2 text-sm w-28">
        </div>
        <button type="submit" class="px-4 py-2 bg-orange-600 text-white rounded-lg text-sm font-medium hover:bg-orange-700">Filter</button>
        <a href="{{ route('readings.z') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300">Reset</a>
        <a href="{{ route('readings.z.export.csv', request()->query()) }}" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">Export CSV</a>
    </form>

    <!-- BIR Summary -->
    @if($readings->count())
    <div class="bg-orange-50 border border-orange-200 rounded-lg p-5">
        <div class="flex items-center gap-2 mb-3">
            <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            <h3 class="font-bold text-orange-800">BIR Z-Reading Summary (This Page)</h3>
        </div>
        <div class="grid grid-cols-5 gap-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-orange-700">{{ $readings->count() }}</div>
                <div class="text-xs text-gray-500 mt-1">Z-Readings</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-green-700">&#8369;{{ number_format($readings->sum('total_sales'), 2) }}</div>
                <div class="text-xs text-gray-500 mt-1">Gross Sales</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-700">{{ $readings->sum('transaction_count') }}</div>
                <div class="text-xs text-gray-500 mt-1">Transactions</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-red-600">&#8369;{{ number_format($readings->sum('discount_total'), 2) }}</div>
                <div class="text-xs text-gray-500 mt-1">Total Discounts</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-orange-600">&#8369;{{ number_format($readings->sum('void_total'), 2) }}</div>
                <div class="text-xs text-gray-500 mt-1">Total Voids</div>
            </div>
        </div>
    </div>
    @endif

    <!-- Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3">Z#</th>
                    <th class="px-4 py-3">Branch</th>
                    <th class="px-4 py-3">Cashier</th>
                    <th class="px-4 py-3">Generated At</th>
                    <th class="px-4 py-3 text-right">Total Sales</th>
                    <th class="px-4 py-3 text-right">Txns</th>
                    <th class="px-4 py-3 text-right">Discounts</th>
                    <th class="px-4 py-3 text-right">Voids</th>
                    <th class="px-4 py-3">Payment Breakdown</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($readings as $r)
                <tr class="hover:bg-orange-50">
                    <td class="px-4 py-3">
                        <span class="inline-block px-2 py-1 bg-orange-100 text-orange-800 font-bold rounded text-xs">Z-{{ $r->z_count }}</span>
                    </td>
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
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-4 py-12 text-center text-gray-400">No Z-readings found. Generate one from the POS cashier at end of day.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $readings->withQueryString()->links() }}

    <!-- BIR Compliance Note -->
    <div class="bg-gray-50 border rounded-lg p-4 text-xs text-gray-500">
        <strong>BIR Compliance Note:</strong> Z-Readings must be generated once per business day per branch.
        Each Z-Reading increments a sequential Z-Count per branch and resets daily totals.
        These records must be retained for audit purposes as required by Philippine revenue regulations.
    </div>
</div>
@endsection
