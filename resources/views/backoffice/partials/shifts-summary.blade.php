@props(['shiftSummary'])

<div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-lg">Today's Shifts</h3>
        <a href="{{ route('backoffice.shifts.dashboard') }}" class="text-blue-600 hover:underline text-sm">View All &rarr;</a>
    </div>
    @php
        $variance = (float) ($shiftSummary['variance'] ?? 0);
        $varianceTone = $variance == 0 ? 'green-panel' : ($variance > 0 ? 'blue-panel' : 'red-panel');
        $variancePrefix = $variance >= 0 ? '+' : '';
        $varianceDisplay = $variancePrefix . '₱' . number_format($variance, 2);
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <x-dashboard.card title="Total" :value="$shiftSummary['total']" tone="muted" class="text-center p-3 !shadow-none" />
        <x-dashboard.card title="Open" :value="$shiftSummary['open']" tone="green-panel" class="text-center p-3 !shadow-none" />
        <x-dashboard.card title="Closed" :value="$shiftSummary['closed']" tone="muted" class="text-center p-3 !shadow-none" />
        <x-dashboard.card title="Sales" :value="'₱' . number_format((float) ($shiftSummary['sales'] ?? 0), 2)" tone="blue-panel" class="text-center p-3 !shadow-none" />
        <x-dashboard.card title="Variance" :value="$varianceDisplay" :tone="$varianceTone" class="text-center p-3 !shadow-none" />
    </div>
</div>
