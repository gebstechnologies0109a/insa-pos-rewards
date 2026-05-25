@extends('layouts.backoffice')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Products</h1>
    <div class="flex gap-2">
        <a href="{{ route('admin.products.export') }}" class="px-4 py-2 bg-green-700 text-white rounded hover:bg-green-800 text-sm">Export XLSX</a>
        <button onclick="document.getElementById('importModal').classList.remove('hidden')" class="px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700 text-sm">Import CSV/XLSX</button>
        <a href="{{ route('admin.products.create') }}" class="px-4 py-2 bg-blue-700 text-white rounded hover:bg-blue-800 text-sm">Add Product</a>
    </div>
</div>

<!-- Import Modal -->
<div id="importModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <h2 class="text-lg font-bold mb-4">Import Products</h2>
        <p class="text-sm text-gray-500 mb-4">Upload a CSV or XLSX file with columns: <code class="bg-gray-100 px-1 rounded">name, sku, barcode, price, category, active</code></p>
        <form method="POST" action="{{ route('admin.products.import') }}" enctype="multipart/form-data">
            @csrf
            <input type="file" name="file" accept=".csv,.xlsx" required
                   class="w-full p-2 border rounded mb-4">
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('importModal').classList.add('hidden')"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-700 text-white rounded hover:bg-blue-800">Import</button>
            </div>
        </form>
    </div>
</div>

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" action="{{ route('admin.products.index') }}" class="flex gap-3">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, SKU, barcode..."
               class="flex-1 p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
        <select name="category" class="p-2 border rounded">
            <option value="">All Categories</option>
            @foreach($categories as $cat)
            <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded hover:bg-gray-800">Filter</button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left p-3 font-medium">Name</th>
                <th class="text-left p-3 font-medium">SKU</th>
                <th class="text-left p-3 font-medium">Barcode</th>
                <th class="text-right p-3 font-medium">Price</th>
                <th class="text-left p-3 font-medium">Category</th>
                <th class="text-center p-3 font-medium">Status</th>
                <th class="text-right p-3 font-medium">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
            <tr class="border-t hover:bg-gray-50">
                <td class="p-3 font-medium">{{ $product->name }}</td>
                <td class="p-3 text-gray-600 font-mono text-xs">{{ $product->sku ?? '—' }}</td>
                <td class="p-3 text-gray-600 font-mono text-xs">{{ $product->barcode ?? '—' }}</td>
                <td class="p-3 text-right font-mono">&#8369;{{ number_format($product->price, 2) }}</td>
                <td class="p-3 text-gray-600">{{ $product->category?->name ?? '—' }}</td>
                <td class="p-3 text-center">
                    <span class="px-2 py-1 rounded text-xs font-medium {{ $product->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $product->active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td class="p-3 text-right">
                    <a href="{{ route('admin.products.edit', $product) }}" class="text-blue-600 hover:underline text-xs mr-2">Edit</a>
                    @if($product->active)
                    <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="inline"
                          onsubmit="return confirm('Deactivate this product?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline text-xs">Deactivate</button>
                    </form>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="p-6 text-center text-gray-400">No products found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $products->links() }}</div>
@endsection
