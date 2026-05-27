@extends('layouts.backoffice')

@section('page-title', 'Reorder Forecast')

@section('content')
<h1 class="text-2xl font-bold mb-6">Reorder Forecast</h1>

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <x-branch-selector :branch-id="$branchId" :branches="$branches" :auto-submit="false" />
        <div>
            <label class="block text-xs text-gray-500 mb-1">Lookback (days)</label>
            <input type="number" name="lookback" value="{{ $lookback }}" min="7" max="90" class="p-2 border rounded w-24">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Cover (days)</label>
            <input type="number" name="cover" value="{{ $cover }}" min="7" max="60" class="p-2 border rounded w-24">
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded">Calculate</button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left p-3">Product</th>
                <th class="text-right p-3">Stock</th>
                <th class="text-right p-3">Daily use</th>
                <th class="text-right p-3">Days to zero</th>
                <th class="text-right p-3">Suggested reorder</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
            <tr class="border-t">
                <td class="p-3">{{ $row['name'] }}</td>
                <td class="p-3 text-right font-mono">{{ $row['current_stock'] }}</td>
                <td class="p-3 text-right font-mono">{{ $row['daily_consumption'] }}</td>
                <td class="p-3 text-right">{{ $row['days_to_zero'] ?? '—' }}</td>
                <td class="p-3 text-right font-bold text-blue-700">{{ $row['suggested_reorder'] }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="p-6 text-center text-gray-400">No consumption data for this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
