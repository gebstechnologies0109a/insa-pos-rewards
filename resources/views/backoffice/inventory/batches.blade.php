@extends('layouts.backoffice')

@section('page-title', 'Inventory Batches')

@section('content')
<h1 class="text-2xl font-bold mb-6">Inventory Batches</h1>

@if(session('success'))
<div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
@endif

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" action="{{ route('backoffice.inventory.batches') }}" class="flex flex-wrap gap-3 items-end">
        <x-branch-selector :branch-id="$branchId" :branches="$branches" :auto-submit="false" />
        <div>
            <label class="block text-xs text-gray-500 mb-1">Product</label>
            <select name="product_id" class="p-2 border rounded">
                <option value="">All</option>
                @foreach($products as $p)
                <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Supplier</label>
            <input type="text" name="supplier" value="{{ request('supplier') }}" class="p-2 border rounded" placeholder="Supplier name">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Expiry</label>
            <select name="expiry_filter" class="p-2 border rounded">
                <option value="">All</option>
                <option value="expired" {{ request('expiry_filter') === 'expired' ? 'selected' : '' }}>Expired</option>
                <option value="7d" {{ request('expiry_filter') === '7d' ? 'selected' : '' }}>Within 7 days</option>
                <option value="30d" {{ request('expiry_filter') === '30d' ? 'selected' : '' }}>8–30 days</option>
                <option value="none" {{ request('expiry_filter') === 'none' ? 'selected' : '' }}>No expiry</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded">Filter</button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left p-3">Product</th>
                <th class="text-left p-3">Batch</th>
                <th class="text-left p-3">Expiry</th>
                <th class="text-right p-3">Qty</th>
                <th class="text-left p-3">Supplier</th>
                <th class="text-center p-3">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($batches as $batch)
            <tr class="border-t">
                <td class="p-3">{{ $batch->product->name ?? '—' }}</td>
                <td class="p-3 font-mono text-xs">{{ $batch->batch_code ?? '#'.$batch->id }}</td>
                <td class="p-3 {{ $batch->isExpired() ? 'text-red-600 font-medium' : ($batch->isNearExpiry() ? 'text-yellow-600' : '') }}">
                    {{ $batch->expiry_date?->format('Y-m-d') ?? '—' }}
                </td>
                <td class="p-3 text-right font-mono">{{ $batch->quantity }}</td>
                <td class="p-3 text-gray-600">{{ $batch->supplier_name ?? '—' }}</td>
                <td class="p-3 text-center">
                    <a href="{{ route('backoffice.inventory.batches.edit', $batch) }}" class="text-blue-600 hover:underline">Edit</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="p-6 text-center text-gray-400">No batches found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $batches->links() }}</div>
@endsection
