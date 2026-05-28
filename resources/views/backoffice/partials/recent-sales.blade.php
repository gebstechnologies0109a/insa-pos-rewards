@props(['recentSales'])

<div class="bg-white rounded-lg shadow p-6">
    <h3 class="font-semibold text-lg mb-4">Recent Sales</h3>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b">
                <th class="text-left py-2">Sale #</th>
                <th class="text-right py-2">Total</th>
                <th class="text-right py-2">Time</th>
            </tr>
        </thead>
        <tbody>
            @forelse($recentSales as $sale)
            <tr class="border-b">
                <td class="py-2 font-mono text-xs">{{ $sale->sale_number ?? '—' }}</td>
                <td class="py-2 text-right">&#8369;{{ number_format((float) $sale->total, 2) }}</td>
                <td class="py-2 text-right text-gray-500 text-xs">{{ $sale->sold_at?->diffForHumans() ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="3" class="py-4 text-center text-gray-400">No sales yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
