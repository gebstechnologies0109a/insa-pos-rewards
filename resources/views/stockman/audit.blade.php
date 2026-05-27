@extends('layouts.stockman')

@section('content')
<h1 class="text-2xl font-bold mb-6">Stock Audit</h1>

@if(session('success'))
<div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
@endif

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" action="{{ route('stockman.audit') }}" class="flex flex-wrap gap-3 items-end">
        <x-branch-selector :branch-id="$branchId" :branches="$branches" :auto-submit="false" />
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs text-gray-500 mb-1">Scan / search product</label>
            <input type="text" name="search" value="{{ $search }}" autofocus
                   placeholder="Barcode, SKU, or name..."
                   class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded">Find</button>
    </form>
</div>

@if($search && !$product)
<div class="p-4 bg-yellow-50 text-yellow-800 rounded">No product found for &ldquo;{{ $search }}&rdquo;.</div>
@endif

@if($product)
<div class="mb-4">
    <h2 class="text-lg font-semibold">{{ $product->name }}</h2>
    <p class="text-sm text-gray-500">SKU: {{ $product->sku ?? '—' }} · System stock: {{ app(\App\Services\Inventory\InventoryService::class)->getStockOnHand($branchId, $product->id) }}</p>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left p-3">Batch</th>
                <th class="text-left p-3">Expiry</th>
                <th class="text-right p-3">System qty</th>
                <th class="text-left p-3">Counted qty</th>
            </tr>
        </thead>
        <tbody>
            @forelse($batches as $batch)
            <tr class="border-t">
                <td class="p-3 font-mono text-xs">{{ $batch->batch_code ?? '#'.$batch->id }}</td>
                <td class="p-3">{{ $batch->expiry_date?->format('Y-m-d') ?? '—' }}</td>
                <td class="p-3 text-right font-mono">{{ $batch->quantity }}</td>
                <td class="p-3">
                    <form method="POST" action="{{ route('stockman.audit.update') }}" class="flex gap-2 items-center">
                        @csrf
                        <input type="hidden" name="batch_id" value="{{ $batch->id }}">
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                        <input type="hidden" name="search_q" value="{{ $search }}">
                        <input type="number" step="0.001" name="quantity" value="{{ $batch->quantity }}" class="w-24 p-1 border rounded text-right">
                        <input type="text" name="reason" placeholder="Reason" required class="flex-1 p-1 border rounded text-sm">
                        <button type="submit" class="px-3 py-1 bg-blue-600 text-white rounded text-sm">Save</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="4" class="p-6 text-center text-gray-400">No batches — stock may be movement-only.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endif
@endsection
