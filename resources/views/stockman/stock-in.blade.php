@extends('layouts.stockman')

@section('content')
<h1 class="text-2xl font-bold mb-6">Stock In</h1>

<div class="bg-white rounded-lg shadow p-6" x-data="stockInForm()">
    <form method="POST" action="{{ route('stockman.stock-in.store') }}" @submit="validateForm($event)">
        @csrf

        @if($branches->isNotEmpty())
        <div class="mb-6">
            <label for="branch_id" class="block font-medium text-gray-800 mb-1">Branch</label>
            <select id="branch_id" name="branch_id" required
                    class="w-full max-w-md p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                @foreach($branches as $branch)
                <option value="{{ $branch->id }}" {{ (int) old('branch_id', $defaultBranchId) === $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        @endif

        <div class="mb-6">
            <label for="supplier_name" class="block font-medium text-gray-800 mb-1">Supplier Name (optional)</label>
            <input type="text" id="supplier_name" name="supplier_name" value="{{ old('supplier_name') }}"
                   class="w-full max-w-md p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
        </div>

        <h3 class="font-semibold mb-3">Items</h3>

        <template x-for="(item, index) in items" :key="index">
            <div class="flex gap-3 mb-3 items-end">
                <div class="flex-1">
                    <label class="block text-xs text-gray-500 mb-1" x-show="index === 0">Product</label>
                    <select :name="'items['+index+'][product_id]'" required
                            class="w-full p-2 border rounded text-sm">
                        <option value="">Select product...</option>
                        @foreach($products as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} {{ $p->sku ? '(' . $p->sku . ')' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-28">
                    <label class="block text-xs text-gray-500 mb-1" x-show="index === 0">Qty</label>
                    <input type="number" :name="'items['+index+'][qty]'" min="1" required
                           class="w-full p-2 border rounded text-sm text-right">
                </div>
                <div class="w-32">
                    <label class="block text-xs text-gray-500 mb-1" x-show="index === 0">Unit Cost</label>
                    <input type="number" :name="'items['+index+'][cost]'" min="0" step="0.01" required
                           class="w-full p-2 border rounded text-sm text-right">
                </div>
                <button type="button" @click="removeItem(index)" x-show="items.length > 1"
                        class="px-3 py-2 text-red-600 hover:text-red-800 font-bold">X</button>
            </div>
        </template>

        <button type="button" @click="addItem()"
                class="mb-6 px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 text-sm">+ Add Item</button>

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 p-3 rounded mb-4 text-sm">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-3 bg-green-700 text-white font-semibold rounded hover:bg-green-800">
                Record Stock In
            </button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function stockInForm() {
    return {
        items: [{}],
        addItem() { this.items.push({}); },
        removeItem(i) { this.items.splice(i, 1); },
        validateForm(e) {
            if (this.items.length === 0) {
                e.preventDefault();
                alert('Add at least one item.');
            }
        }
    };
}
</script>
@endsection
