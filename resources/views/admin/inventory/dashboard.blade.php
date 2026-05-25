@extends('layouts.backoffice')

@section('content')
<h1 class="text-2xl font-bold mb-6">Inventory Dashboard</h1>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Total Products</div>
        <div class="text-2xl font-bold">{{ $summary->total_products ?? 0 }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Out of Stock</div>
        <div class="text-2xl font-bold text-red-600">{{ $summary->out_of_stock ?? 0 }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-500">Low Stock (&le; 10)</div>
        <div class="text-2xl font-bold text-yellow-600">{{ $summary->low_stock ?? 0 }}</div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" action="{{ route('admin.inventory.dashboard') }}" class="flex flex-wrap gap-3 items-end">
        <x-branch-selector :branch-id="$branchId" :branches="$branches" :auto-submit="false" />
        <div>
            <label class="block text-xs text-gray-500 mb-1">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Product name, SKU..."
                   class="p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Category</label>
            <select name="category" class="p-2 border rounded">
                <option value="">All</option>
                @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Stock Level</label>
            <select name="stock_filter" class="p-2 border rounded">
                <option value="">All</option>
                <option value="low" {{ request('stock_filter') === 'low' ? 'selected' : '' }}>Low Stock (&le; 10)</option>
                <option value="out" {{ request('stock_filter') === 'out' ? 'selected' : '' }}>Out of Stock</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded hover:bg-gray-800">Filter</button>
    </form>
</div>

<!-- Inventory Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left p-3 font-medium">Product</th>
                <th class="text-left p-3 font-medium">SKU</th>
                <th class="text-left p-3 font-medium">Barcode</th>
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
                <td class="p-3 text-gray-600 font-mono text-xs">{{ $p->barcode ?? '—' }}</td>
                <td class="p-3 text-right font-mono font-bold {{ $stock <= 0 ? 'text-red-600' : ($stock <= 10 ? 'text-yellow-600' : 'text-green-700') }}">
                    {{ $stock }}
                </td>
                <td class="p-3 text-center">
                    @if($stock <= 0)
                    <span class="px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800">Out of Stock</span>
                    @elseif($stock <= 10)
                    <span class="px-2 py-1 rounded text-xs font-medium bg-yellow-100 text-yellow-800">Low</span>
                    @else
                    <span class="px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">In Stock</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="p-6 text-center text-gray-400">No products found for this branch.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $products->links() }}</div>
@endsection
