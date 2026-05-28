<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left p-3">Product</th>
                <th class="text-right p-3">Stock on hand</th>
                <th class="text-left p-3">Last sale</th>
            </tr>
        </thead>
        <tbody>
            @forelse($slowMoving as $row)
            <tr class="border-t">
                <td class="p-3">{{ $row['product']->name ?? '—' }}</td>
                <td class="p-3 text-right font-mono">{{ $row['stock'] }}</td>
                <td class="p-3">{{ $row['last_sale_at'] ?? 'Never' }}</td>
            </tr>
            @empty
            <tr><td colspan="3" class="p-6 text-center text-gray-400">No slow-moving products with stock.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
