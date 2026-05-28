@extends('layouts.backoffice')

@section('page-title', 'Inventory Forecast')

@section('content')
<h1 class="text-2xl font-bold mb-6">Inventory Forecast</h1>

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" action="{{ route('backoffice.inventory.forecast') }}" class="flex flex-wrap gap-3 items-end">
        <x-branch-selector :branch-id="$branchId" :branches="$branches" :auto-submit="false" />
        <div>
            <label class="block text-xs text-gray-500 mb-1">Product (FEFO detail)</label>
            <select name="product_id" class="p-2 border rounded min-w-[200px]">
                <option value="">— Reorder list only —</option>
                @foreach($products as $p)
                <option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Lookback (days)</label>
            <input type="number" name="lookback" value="{{ $lookback }}" min="7" max="90" class="p-2 border rounded w-24">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Cover (days)</label>
            <input type="number" name="cover" value="{{ $cover }}" min="7" max="60" class="p-2 border rounded w-24">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Horizon</label>
            <input type="number" name="horizon" value="{{ $horizon }}" min="7" max="90" class="p-2 border rounded w-24">
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded">Calculate</button>
    </form>
</div>

@if($productDetail)
<div class="bg-white rounded-lg shadow p-6 mb-6 border-l-4 border-blue-500">
    <h2 class="text-lg font-semibold mb-2">{{ $productDetail['name'] }} <span class="text-sm text-gray-500 font-mono">{{ $productDetail['sku'] }}</span></h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mb-4">
        <div><span class="text-gray-500">On hand</span><div class="font-bold">{{ $productDetail['on_hand'] }}</div></div>
        <div><span class="text-gray-500">Daily use</span><div class="font-bold">{{ $productDetail['daily_consumption'] }}</div></div>
        <div><span class="text-gray-500">Days to zero</span><div class="font-bold">{{ $productDetail['days_until_depleted'] ?? '—' }}</div></div>
        <div><span class="text-gray-500">Suggested reorder</span><div class="font-bold text-blue-700">{{ $productDetail['suggested_reorder'] }}</div></div>
        <div><span class="text-gray-500">Earliest expiry</span><div class="font-bold">{{ $productDetail['earliest_expiry'] ?? '—' }}</div></div>
        <div><span class="text-gray-500">Near expiry</span><div class="font-bold">{{ $productDetail['near_expiry'] ? 'Yes' : 'No' }}</div></div>
    </div>
    @if(!empty($productDetail['batches']))
    <h3 class="text-sm font-medium text-gray-700 mb-2">FEFO batches</h3>
    <table class="w-full text-sm">
        <thead class="bg-gray-50"><tr>
            <th class="text-left p-2">Batch</th><th class="text-left p-2">Expiry</th><th class="text-right p-2">Qty</th>
        </tr></thead>
        <tbody>
            @foreach($productDetail['batches'] as $b)
            <tr class="border-t">
                <td class="p-2 font-mono text-xs">{{ $b['batch_code'] ?? '#'.$b['id'] }}</td>
                <td class="p-2">{{ $b['expiry_date'] ?? '—' }}</td>
                <td class="p-2 text-right">{{ $b['quantity'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>
@endif

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left p-3">Product</th>
                <th class="text-right p-3">Stock</th>
                <th class="text-right p-3">Daily use</th>
                <th class="text-right p-3">Days to zero</th>
                <th class="text-right p-3">Suggested reorder</th>
                <th class="text-center p-3">Detail</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
            <tr class="border-t hover:bg-gray-50">
                <td class="p-3">{{ $row['name'] }}</td>
                <td class="p-3 text-right font-mono">{{ $row['current_stock'] }}</td>
                <td class="p-3 text-right font-mono">{{ $row['daily_consumption'] }}</td>
                <td class="p-3 text-right">{{ $row['days_to_zero'] ?? '—' }}</td>
                <td class="p-3 text-right font-bold text-blue-700">{{ $row['suggested_reorder'] }}</td>
                <td class="p-3 text-center">
                    <a href="{{ route('backoffice.inventory.forecast', array_merge(request()->query(), ['product_id' => $row['product_id']])) }}"
                       class="text-blue-600 hover:underline text-xs">FEFO</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="p-6 text-center text-gray-400">No consumption data for this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
