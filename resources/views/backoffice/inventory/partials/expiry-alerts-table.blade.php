<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left p-3">Product</th>
                <th class="text-left p-3">Type</th>
                <th class="text-left p-3">Expiry</th>
                <th class="text-right p-3">Batch qty</th>
                <th class="text-center p-3">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($alerts as $alert)
            <tr class="border-t">
                <td class="p-3">{{ $alert->product->name ?? '—' }}</td>
                <td class="p-3">{{ str_replace('_', ' ', $alert->alert_type) }}</td>
                <td class="p-3">{{ $alert->expiry_date?->format('Y-m-d') }}</td>
                <td class="p-3 text-right font-mono">{{ $alert->quantity }}</td>
                <td class="p-3 text-center space-x-2">
                    @if(!$alert->handled_at)
                    <form method="POST" action="{{ route('backoffice.inventory.expiry.handle', $alert) }}" class="inline">@csrf
                        <button type="submit" class="text-green-600 hover:underline text-xs">Handled</button>
                    </form>
                    <form method="POST" action="{{ route('backoffice.inventory.expiry.snooze', $alert) }}" class="inline">@csrf
                        <input type="hidden" name="days" value="7">
                        <button type="submit" class="text-amber-600 hover:underline text-xs">Snooze 7d</button>
                    </form>
                    @else
                    <span class="text-xs text-gray-400">Handled</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="p-6 text-center text-gray-400">No alerts in this bucket.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if($alerts instanceof \Illuminate\Pagination\LengthAwarePaginator)
<div class="mt-4">{{ $alerts->links() }}</div>
@endif
