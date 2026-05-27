@extends('layouts.backoffice')

@section('page-title', 'Inventory Report')

@section('content')
<h1 class="text-2xl font-bold mb-6">Inventory Report</h1>

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" class="flex gap-3 items-end">
        <x-branch-selector :branch-id="$branchId" :branches="$branches" :auto-submit="false" />
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded">Apply</button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left p-3">Product</th>
                <th class="text-left p-3">SKU</th>
                <th class="text-right p-3">Stock</th>
                <th class="text-left p-3">Earliest expiry</th>
                <th class="text-center p-3">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
            <tr class="border-t">
                <td class="p-3 font-medium">{{ $row['product']->name }}</td>
                <td class="p-3 font-mono text-xs">{{ $row['product']->sku ?? '—' }}</td>
                <td class="p-3 text-right font-mono">{{ $row['stock'] }}</td>
                <td class="p-3">{{ $row['earliest_expiry'] ?? '—' }}</td>
                <td class="p-3 text-center">
                    @if($row['stock'] <= 0)
                    <span class="px-2 py-1 rounded text-xs bg-red-100 text-red-800">Out</span>
                    @elseif($row['low_stock'])
                    <span class="px-2 py-1 rounded text-xs bg-yellow-100 text-yellow-800">Low</span>
                    @else
                    <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">OK</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
