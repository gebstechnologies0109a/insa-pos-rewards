@extends('layouts.backoffice')

@section('page-title', 'Stock Adjustment')

@section('content')
<h1 class="text-2xl font-bold mb-6">Stock Adjustment</h1>

@if(session('success'))
<div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
@endif

<div class="bg-white rounded-lg shadow p-6 max-w-xl">
    <form method="POST" action="{{ route('backoffice.inventory.adjustment.store') }}">
        @csrf
        @if($branches->isNotEmpty())
        <x-branch-selector :branch-id="$branchId" :branches="$branches" :auto-submit="false" />
        @else
        <input type="hidden" name="branch_id" value="{{ $branchId }}">
        @endif

        <div class="mt-4 space-y-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Product</label>
                <select name="product_id" required class="w-full p-2 border rounded" onchange="this.form.method='GET'; this.form.action='{{ route('backoffice.inventory.adjustment') }}'; this.form.submit();">
                    <option value="">Select product</option>
                    @foreach($products as $p)
                    <option value="{{ $p->id }}" {{ (int)$productId === $p->id ? 'selected' : '' }}>{{ $p->name }} ({{ $p->sku ?? 'no SKU' }})</option>
                    @endforeach
                </select>
                @if($currentStock !== null)
                <p class="text-sm text-gray-600 mt-1">Current stock: <strong>{{ $currentStock }}</strong></p>
                @endif
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Direction</label>
                <select name="direction" required class="w-full p-2 border rounded">
                    <option value="in">Stock in (+)</option>
                    <option value="out">Stock out (−)</option>
                    <option value="set">Set exact quantity</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Quantity</label>
                <input type="number" step="0.001" name="qty" required min="0.001" class="w-full p-2 border rounded">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Reason</label>
                <textarea name="reason" required rows="3" class="w-full p-2 border rounded"></textarea>
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Record adjustment</button>
        </div>
    </form>
</div>
@endsection
