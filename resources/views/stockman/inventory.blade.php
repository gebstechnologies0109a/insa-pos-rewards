@extends('layouts.stockman')

@section('content')
<h1 class="text-2xl font-bold mb-6">Inventory</h1>

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" action="{{ route('stockman.inventory') }}" class="flex gap-3 items-end">
        <x-branch-selector :branch-id="$branchId" :branches="$branches" :auto-submit="false" />
        <div>
            <label class="block text-xs text-gray-500 mb-1">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Product name or SKU..."
                   class="p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded hover:bg-gray-800">Filter</button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left p-3 font-medium">Product</th>
                <th class="text-left p-3 font-medium">SKU</th>
                <th class="text-right p-3 font-medium">Stock on Hand</th>
                <th class="text-center p-3 font-medium">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $p)
            @php $stock = (int) $p->stock_on_hand; @endphp
            <tr class="border-t hover:bg-gray-50">
                <td class="p-3 font-medium">{{ $p->name }}</td>
                <td class="p-3 text-gray-600 font-mono text-xs">{{ $p->sku ?? '—' }}</td>
                <td class="p-3 text-right font-mono font-bold {{ $stock <= 0 ? 'text-red-600' : ($stock <= 10 ? 'text-yellow-600' : 'text-green-700') }}">{{ $stock }}</td>
                <td class="p-3 text-center">
                    @if($stock <= 0)
                    <span class="px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800">Out</span>
                    @elseif($stock <= 10)
                    <span class="px-2 py-1 rounded text-xs font-medium bg-yellow-100 text-yellow-800">Low</span>
                    @else
                    <span class="px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">OK</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="4" class="p-6 text-center text-gray-400">No products found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $products->links() }}</div>
@endsection
