@extends('layouts.stockman')

@section('content')
<h1 class="text-2xl font-bold mb-6">Stock In</h1>

@if($migrationPending ?? false)
<div class="bg-amber-50 border border-amber-200 text-amber-900 p-4 rounded mb-4 text-sm">
    Stock-in recording requires pending database migrations. Ask your administrator to run
    <code class="bg-amber-100 px-1 rounded">php artisan migrate --force</code> on the server.
</div>
@elseif($batchMigrationPending ?? false)
<div class="bg-amber-50 border border-amber-200 text-amber-900 p-4 rounded mb-4 text-sm">
    Batch and expiry tracking is not enabled yet. Stock-in will still work using legacy stock movements until
    <code class="bg-amber-100 px-1 rounded">inventory_batches</code> is migrated.
</div>
@endif

@if($batchMigrationPending ?? false)
<div class="bg-blue-50 border border-blue-200 text-blue-900 p-4 rounded mb-4 text-sm">
    Batch tracking is not enabled on this server yet. Stock-in will still record quantities using stock movements.
</div>
@endif

<div class="bg-white rounded-lg shadow p-6" x-data="stockInForm({{ ($migrationPending ?? false) ? 'true' : 'false' }})" @click.outside="closeSearch()">
    <form method="POST" action="{{ route('stockman.stock-in.store') }}" @submit="validateForm($event)" @if($migrationPending ?? false) class="opacity-60 pointer-events-none" @endif>
        @csrf

        @if(($branches ?? collect())->isNotEmpty())
        <div class="mb-6">
            <label for="branch_id" class="block font-medium text-gray-800 mb-1">Branch</label>
            <select id="branch_id" name="branch_id" required x-model="branchId"
                    class="w-full max-w-md p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                @foreach($branches as $branch)
                <option value="{{ $branch->id }}" {{ (int) old('branch_id', $defaultBranchId ?? null) === $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
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

        <div class="mb-6 relative">
            <label for="productSearch" class="block text-xs text-gray-500 mb-1">Scan / search product</label>
            <input type="text" id="productSearch" x-ref="searchInput"
                   x-model="searchQuery"
                   @input="onSearchInput()"
                   @keydown="onSearchKeydown($event)"
                   @focus="searchQuery.length >= 1 && searchResults.length && (searchOpen = true)"
                   placeholder="Barcode, SKU, or product name..."
                   autocomplete="off"
                   class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none text-base">
            <p class="mt-1 text-xs text-gray-400">Type to search, use arrow keys and Enter to select.</p>

            <div x-show="searchOpen" x-cloak
                 class="absolute z-20 left-0 right-0 mt-1 bg-white border rounded-lg shadow-lg max-h-72 overflow-y-auto">
                <template x-if="searchLoading">
                    <div class="p-4 text-sm text-gray-500 text-center">Searching...</div>
                </template>
                <template x-if="!searchLoading && searchQuery.length >= 1 && searchResults.length === 0">
                    <div class="p-4 text-sm text-gray-500 text-center">No products found for &ldquo;<span x-text="searchQuery"></span>&rdquo;</div>
                </template>
                <template x-for="(product, idx) in searchResults" :key="product.id">
                    <button type="button"
                            @click="selectProduct(product)"
                            @mouseenter="searchHighlight = idx"
                            :class="searchHighlight === idx ? 'bg-blue-50' : 'hover:bg-gray-50'"
                            class="w-full text-left p-3 border-b last:border-0 flex justify-between items-center gap-3 min-h-[3rem]">
                        <div class="min-w-0">
                            <div class="font-medium text-sm truncate" x-text="product.name"></div>
                            <div class="text-xs text-gray-400 truncate">
                                <span x-show="product.sku" x-text="product.sku"></span>
                                <span x-show="product.sku && product.barcode"> · </span>
                                <span x-show="product.barcode" x-text="product.barcode"></span>
                            </div>
                        </div>
                        <div class="text-xs text-gray-500 shrink-0" x-show="product.price">
                            ₱<span x-text="parseFloat(product.price).toFixed(2)"></span>
                        </div>
                    </button>
                </template>
            </div>
        </div>

        <div x-show="items.length === 0" class="mb-6 p-6 border-2 border-dashed border-gray-200 rounded-lg text-center text-gray-400 text-sm">
            No items yet. Search above to add products to this stock-in.
        </div>

        <template x-for="(item, index) in items" :key="item.uid">
            <div class="flex flex-wrap gap-3 mb-3 items-end border-b border-gray-100 pb-3 last:border-0">
                <input type="hidden" :name="'items['+index+'][product_id]'" :value="item.product_id">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs text-gray-500 mb-1" x-show="index === 0">Product</label>
                    <div class="p-2 border rounded bg-gray-50 text-sm">
                        <div class="font-medium" x-text="item.name"></div>
                        <div class="text-xs text-gray-400" x-show="item.sku" x-text="item.sku"></div>
                    </div>
                </div>
                <div class="w-28">
                    <label class="block text-xs text-gray-500 mb-1" x-show="index === 0">Qty</label>
                    <input type="number" :name="'items['+index+'][qty]'" min="1" required
                           x-model="item.qty"
                           class="w-full p-2 border rounded text-sm text-right">
                </div>
                <div class="w-32">
                    <label class="block text-xs text-gray-500 mb-1" x-show="index === 0">Unit Cost</label>
                    <input type="number" :name="'items['+index+'][cost]'" min="0" step="0.01" required
                           x-model="item.cost"
                           class="w-full p-2 border rounded text-sm text-right">
                </div>
                <button type="button" @click="removeItem(index)"
                        class="px-3 py-2 text-red-600 hover:text-red-800 font-bold min-h-[2.5rem]"
                        title="Remove item">&times;</button>
            </div>
        </template>

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 p-3 rounded mb-4 text-sm">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="flex justify-end mt-6">
            <button type="submit" class="px-6 py-3 bg-green-700 text-white font-semibold rounded hover:bg-green-800 min-h-[3rem]">
                Record Stock In
            </button>
        </div>
    </form>
</div>

<style>[x-cloak] { display: none !important; }</style>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function stockInForm(migrationPending = false) {
    return {
        migrationPending,
        items: [],
        branchId: @json(old('branch_id', $defaultBranchId ?? null)),
        searchQuery: '',
        searchResults: [],
        searchOpen: false,
        searchLoading: false,
        searchHighlight: -1,
        searchTimer: null,
        searchUrl: @json(route('stockman.products.search')),
        nextUid: 1,

        onSearchInput() {
            clearTimeout(this.searchTimer);
            this.searchHighlight = -1;

            if (this.searchQuery.trim().length < 1) {
                this.searchResults = [];
                this.searchOpen = false;
                this.searchLoading = false;
                return;
            }

            this.searchOpen = true;
            this.searchLoading = true;

            this.searchTimer = setTimeout(() => this.fetchProducts(), 250);
        },

        fetchProducts() {
            const q = this.searchQuery.trim();
            if (q.length < 1) {
                this.searchLoading = false;
                return;
            }

            fetch(this.searchUrl + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(res => res.ok ? res.json() : [])
                .then(products => {
                    this.searchResults = Array.isArray(products) ? products : [];
                    this.searchOpen = true;
                    this.searchHighlight = this.searchResults.length ? 0 : -1;
                })
                .catch(() => {
                    this.searchResults = [];
                })
                .finally(() => {
                    this.searchLoading = false;
                });
        },

        selectProduct(product) {
            const existing = this.items.find(i => i.product_id === product.id);
            if (existing) {
                existing.qty = (parseInt(existing.qty, 10) || 0) + 1;
            } else {
                this.items.push({
                    uid: this.nextUid++,
                    product_id: product.id,
                    name: product.name,
                    sku: product.sku || '',
                    qty: 1,
                    cost: '',
                });
            }
            this.clearSearch();
            this.$nextTick(() => this.$refs.searchInput?.focus());
        },

        clearSearch() {
            this.searchQuery = '';
            this.searchResults = [];
            this.searchOpen = false;
            this.searchHighlight = -1;
        },

        closeSearch() {
            this.searchOpen = false;
        },

        onSearchKeydown(e) {
            if (!this.searchOpen || this.searchResults.length === 0) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
                return;
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.searchHighlight = (this.searchHighlight + 1) % this.searchResults.length;
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.searchHighlight = this.searchHighlight <= 0
                    ? this.searchResults.length - 1
                    : this.searchHighlight - 1;
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (this.searchHighlight >= 0 && this.searchResults[this.searchHighlight]) {
                    this.selectProduct(this.searchResults[this.searchHighlight]);
                } else if (this.searchResults.length === 1) {
                    this.selectProduct(this.searchResults[0]);
                }
            } else if (e.key === 'Escape') {
                this.closeSearch();
            }
        },

        removeItem(i) {
            this.items.splice(i, 1);
        },

        validateForm(e) {
            if (this.items.length === 0) {
                e.preventDefault();
                alert('Add at least one product using the search box.');
                this.$refs.searchInput?.focus();
                return;
            }

            for (const item of this.items) {
                if (!item.product_id || !item.qty || item.qty < 1 || item.cost === '' || item.cost < 0) {
                    e.preventDefault();
                    alert('Each item needs a quantity of at least 1 and a unit cost.');
                    return;
                }
            }
        },
    };
}
</script>
@endsection
