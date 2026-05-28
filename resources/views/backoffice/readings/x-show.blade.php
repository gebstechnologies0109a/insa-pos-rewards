@extends('layouts.backoffice')

@section('page-title', 'X-Reading #' . $reading->id)

@section('content')
<div class="space-y-6">

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">X-Reading #{{ $reading->id }}</h1>
        <a href="{{ route('readings.x') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg text-sm font-medium hover:bg-gray-700">Back to Report</a>
    </div>

    <!-- Summary cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-green-700">&#8369;{{ number_format($reading->total_sales, 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">Total Sales</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-gray-700">{{ $reading->transaction_count }}</div>
            <div class="text-xs text-gray-500 mt-1">Transactions</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-red-600">&#8369;{{ number_format($reading->discount_total, 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">Discounts</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-orange-600">&#8369;{{ number_format($reading->void_total, 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">Voids</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Reading info -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="font-semibold text-lg mb-4">Reading Details</h3>
            <dl class="grid grid-cols-2 gap-y-3 text-sm">
                <dt class="text-gray-500">Branch</dt>
                <dd class="font-medium">{{ $reading->branch->name ?? '—' }}</dd>
                <dt class="text-gray-500">Cashier</dt>
                <dd class="font-medium">{{ $reading->cashier->name ?? '—' }}</dd>
                <dt class="text-gray-500">Generated At</dt>
                <dd>{{ $reading->generated_at->format('M d, Y h:i:s A') }}</dd>
                @if($reading->terminal_id)
                <dt class="text-gray-500">Terminal</dt>
                <dd class="font-mono">{{ $reading->terminal_id }}</dd>
                @endif
            </dl>
        </div>

        <!-- Payment breakdown -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="font-semibold text-lg mb-4">Payment Breakdown</h3>
            @if($reading->payment_breakdown)
                <dl class="space-y-2 text-sm">
                    @foreach($reading->payment_breakdown as $method => $amount)
                        @if($amount > 0)
                        <div class="flex justify-between items-center py-1 border-b border-gray-100 last:border-0">
                            <dt class="text-gray-600">{{ ucwords(str_replace('_', ' ', $method)) }}</dt>
                            <dd class="font-mono font-semibold">&#8369;{{ number_format($amount, 2) }}</dd>
                        </div>
                        @endif
                    @endforeach
                </dl>
            @else
                <p class="text-gray-400 text-sm">No payment breakdown recorded.</p>
            @endif
        </div>
    </div>

    <!-- Completed sales -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-lg">Completed Sales ({{ $completedSales->count() }})</h3>
            <p class="text-xs text-gray-500 mt-1">Sales for this cashier on {{ $reading->generated_at->format('M d, Y') }} through {{ $reading->generated_at->format('h:i:s A') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3">Sale #</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                        <th class="px-4 py-3 text-right">Discount</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-center">Payment</th>
                        <th class="px-4 py-3">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($completedSales as $sale)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs">{{ $sale->sale_number }}</td>
                        <td class="px-4 py-3 text-right font-mono">&#8369;{{ number_format($sale->subtotal, 2) }}</td>
                        <td class="px-4 py-3 text-right font-mono text-red-600">&#8369;{{ number_format($sale->discount_total, 2) }}</td>
                        <td class="px-4 py-3 text-right font-mono font-semibold">&#8369;{{ number_format($sale->total, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">{{ ucfirst(str_replace('_', ' ', $sale->payment_method)) }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $sale->sold_at?->format('h:i:s A') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">No completed sales in this snapshot.</td>
                    </tr>
                    @endforelse
                </tbody>
                @if($completedSales->isNotEmpty())
                <tfoot class="bg-gray-50 font-bold">
                    <tr>
                        <td class="px-4 py-3">Total</td>
                        <td class="px-4 py-3 text-right font-mono">&#8369;{{ number_format($completedSales->sum('subtotal'), 2) }}</td>
                        <td class="px-4 py-3 text-right font-mono">&#8369;{{ number_format($completedSales->sum('discount_total'), 2) }}</td>
                        <td class="px-4 py-3 text-right font-mono">&#8369;{{ number_format($completedSales->sum('total'), 2) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    @if($voidedSales->isNotEmpty())
    <!-- Voided sales -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-lg text-orange-700">Voided Sales ({{ $voidedSales->count() }})</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3">Sale #</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-center">Payment</th>
                        <th class="px-4 py-3">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($voidedSales as $sale)
                    <tr class="hover:bg-orange-50">
                        <td class="px-4 py-3 font-mono text-xs">{{ $sale->sale_number }}</td>
                        <td class="px-4 py-3 text-right font-mono font-semibold text-orange-700">&#8369;{{ number_format($sale->total, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">{{ ucfirst(str_replace('_', ' ', $sale->payment_method)) }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $sale->sold_at?->format('h:i:s A') }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 font-bold">
                    <tr>
                        <td class="px-4 py-3">Void Total</td>
                        <td class="px-4 py-3 text-right font-mono text-orange-700">&#8369;{{ number_format($voidedSales->sum('total'), 2) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

</div>
@endsection
