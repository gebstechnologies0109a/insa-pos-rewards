@extends('layouts.backoffice')

@section('page-title', 'Edit Batch')

@section('content')
<h1 class="text-2xl font-bold mb-6">Edit Batch #{{ $batch->id }}</h1>

<div class="grid md:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="font-semibold mb-4">Batch details</h2>
        <form method="POST" action="{{ route('backoffice.inventory.batches.update', $batch) }}">
            @csrf
            @method('PUT')
            <div class="space-y-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Product</label>
                    <div class="font-medium">{{ $batch->product->name }}</div>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Batch code</label>
                    <input type="text" name="batch_code" value="{{ old('batch_code', $batch->batch_code) }}" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Expiry date</label>
                    <input type="date" name="expiry_date" value="{{ old('expiry_date', $batch->expiry_date?->format('Y-m-d')) }}" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Cost price</label>
                    <input type="number" step="0.01" name="cost_price" value="{{ old('cost_price', $batch->cost_price) }}" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Supplier</label>
                    <input type="text" name="supplier_name" value="{{ old('supplier_name', $batch->supplier_name) }}" class="w-full p-2 border rounded">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Save details</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="font-semibold mb-4">Adjust quantity ({{ $batch->quantity }} on hand)</h2>
        <form method="POST" action="{{ route('backoffice.inventory.batches.adjust', $batch) }}">
            @csrf
            <div class="space-y-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">New quantity</label>
                    <input type="number" step="0.001" name="quantity" value="{{ $batch->quantity }}" required class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Reason</label>
                    <textarea name="reason" required rows="3" class="w-full p-2 border rounded" placeholder="Count correction, damage, etc."></textarea>
                </div>
                <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded">Apply adjustment</button>
            </div>
        </form>
    </div>
</div>

<p class="mt-4"><a href="{{ route('backoffice.inventory.batches', ['branch_id' => $batch->branch_id]) }}" class="text-blue-600 hover:underline">&larr; Back to batches</a></p>
@endsection
