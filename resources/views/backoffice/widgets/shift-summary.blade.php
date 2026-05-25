<div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-lg">Today's Shifts</h3>
        <a href="{{ route('backoffice.shifts.dashboard') }}" class="text-blue-600 hover:underline text-sm">View All &rarr;</a>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="text-center p-3 bg-gray-50 rounded">
            <div class="text-xs text-gray-500 uppercase">Total</div>
            <div class="text-2xl font-bold mt-1">{{ $shiftSummary['total'] }}</div>
        </div>
        <div class="text-center p-3 bg-green-50 rounded">
            <div class="text-xs text-green-600 uppercase">Open</div>
            <div class="text-2xl font-bold mt-1 text-green-600">{{ $shiftSummary['open'] }}</div>
        </div>
        <div class="text-center p-3 bg-gray-50 rounded">
            <div class="text-xs text-gray-500 uppercase">Closed</div>
            <div class="text-2xl font-bold mt-1">{{ $shiftSummary['closed'] }}</div>
        </div>
        <div class="text-center p-3 bg-blue-50 rounded">
            <div class="text-xs text-blue-600 uppercase">Sales</div>
            <div class="text-2xl font-bold mt-1 text-blue-700">&#8369;{{ number_format($shiftSummary['sales'], 2) }}</div>
        </div>
        <div class="text-center p-3 rounded {{ $shiftSummary['variance'] == 0 ? 'bg-green-50' : ($shiftSummary['variance'] > 0 ? 'bg-blue-50' : 'bg-red-50') }}">
            <div class="text-xs uppercase {{ $shiftSummary['variance'] == 0 ? 'text-green-600' : ($shiftSummary['variance'] > 0 ? 'text-blue-600' : 'text-red-600') }}">Variance</div>
            @php $sv = $shiftSummary['variance']; @endphp
            <div class="text-2xl font-bold mt-1 {{ $sv == 0 ? 'text-green-600' : ($sv > 0 ? 'text-blue-600' : 'text-red-600') }}">
                {{ $sv >= 0 ? '+' : '' }}&#8369;{{ number_format($sv, 2) }}
            </div>
        </div>
    </div>
</div>
