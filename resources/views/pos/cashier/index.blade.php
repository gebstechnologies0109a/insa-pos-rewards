<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>INSA POS — Cashier</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        [x-cloak] { display: none !important; }
        .product-tile:active { transform: scale(0.95); }
        #posScreen, #checkoutScreen { min-height: 0; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    </style>
</head>
<body class="bg-gray-100 h-screen flex flex-col overflow-hidden" x-data="posApp()" x-cloak>

<!-- HEADER -->
<header class="bg-white shadow px-4 py-2 flex items-center justify-between flex-shrink-0">
    <h1 class="text-lg font-bold text-gray-800">INSA POS</h1>
    <div class="flex items-center gap-3 text-sm text-gray-600">
        @auth
        <span class="font-medium">{{ auth()->user()->name }}</span>
        <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">{{ ucfirst(auth()->user()->role) }}</span>
        <span>Branch: <strong>{{ auth()->user()->branch?->name ?? 'N/A' }}</strong></span>
        @if(auth()->user()->canAccessBackoffice())
        <a href="{{ route('backoffice.dashboard') }}" class="text-blue-600 hover:underline">Back Office</a>
        @endif
        <form method="POST" action="{{ route('logout') }}" class="inline">@csrf
            <button type="submit" class="text-red-600 hover:underline">Logout</button>
        </form>
        @endauth
    </div>
</header>

<!-- SHIFT BAR -->
<div class="px-4 pt-2 flex-shrink-0">
    <div x-show="!activeShift" class="bg-yellow-50 border border-yellow-300 rounded-lg p-3 flex items-center justify-between">
        <div>
            <div class="font-semibold text-yellow-800">No Active Shift</div>
            <div class="text-sm text-yellow-600">Open a shift to start processing sales.</div>
        </div>
        <button @click="openShift()" class="px-5 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">Open Shift</button>
    </div>
    <div x-show="activeShift" class="bg-green-50 border border-green-300 rounded-lg p-3 flex items-center justify-between">
        <div>
            <div class="font-semibold text-green-800">Shift Active</div>
            <div class="text-sm text-green-600">
                Opened: <span x-text="activeShift ? new Date(activeShift.opened_at).toLocaleTimeString() : ''"></span> &middot;
                Opening Cash: &#8369;<span x-text="activeShift ? parseFloat(activeShift.opening_cash).toFixed(2) : '0.00'"></span>
            </div>
        </div>
        <button @click="doCloseShift()" class="px-5 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700">Close Shift</button>
    </div>
</div>

<!-- ═══════════ MAIN POS SCREEN ═══════════ -->
<div x-show="screen === 'pos'" id="posScreen" class="flex flex-1 overflow-hidden p-4 gap-4" :class="!activeShift && 'opacity-40 pointer-events-none'">

    <!-- LEFT: PRODUCTS -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Search + Category Filters -->
        <div class="flex gap-2 mb-3">
            <input type="text" x-model="searchQuery" @input.debounce.200ms="filterProducts()" placeholder="Search products..."
                   class="flex-1 p-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            <select x-model="selectedCategory" @change="filterProducts()" class="p-2.5 border rounded-lg text-sm bg-white">
                <option value="">All Categories</option>
                <template x-for="cat in categories" :key="cat.id">
                    <option :value="cat.id" x-text="cat.name"></option>
                </template>
            </select>
        </div>

        <!-- Product Grid -->
        <div class="flex-1 overflow-y-auto">
            <div class="grid grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2">
                <template x-for="product in filteredProducts" :key="product.id">
                    <button @click="addToCart(product)"
                            class="product-tile bg-white rounded-lg shadow hover:shadow-md border border-gray-200 p-3 text-left transition-all flex flex-col"
                            :class="product.stock <= 0 && 'opacity-40 cursor-not-allowed'"
                            :disabled="product.stock <= 0">
                        <div class="font-medium text-sm leading-tight truncate" x-text="product.name"></div>
                        <div class="text-xs text-gray-400 mt-1" x-text="product.sku"></div>
                        <div class="mt-auto pt-2 flex items-end justify-between">
                            <span class="font-bold text-blue-700" x-text="'₱' + parseFloat(product.price).toFixed(2)"></span>
                            <span class="text-xs px-1.5 py-0.5 rounded"
                                  :class="product.stock > 10 ? 'bg-green-100 text-green-700' : (product.stock > 0 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700')"
                                  x-text="product.stock > 0 ? product.stock + ' in stock' : 'Out of stock'"></span>
                        </div>
                    </button>
                </template>
            </div>
            <div x-show="filteredProducts.length === 0" class="text-center py-12 text-gray-400">No products found.</div>
        </div>
    </div>

    <!-- RIGHT: CART -->
    <div class="w-80 xl:w-96 bg-white rounded-lg shadow flex flex-col flex-shrink-0">
        <div class="p-4 border-b">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-lg">Cart</h2>
                <span class="text-sm text-gray-500" x-text="cart.length + ' item(s)'"></span>
            </div>
        </div>

        <!-- Cart Items -->
        <div class="flex-1 overflow-y-auto p-3 space-y-2">
            <template x-for="(item, idx) in cart" :key="item.product_id">
                <div class="bg-gray-50 rounded-lg p-3 flex gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-sm truncate" x-text="item.product_name"></div>
                        <div class="text-xs text-gray-400" x-text="'₱' + item.price.toFixed(2) + ' each'"></div>
                        <div class="flex items-center gap-2 mt-1.5">
                            <button @click="changeQty(idx, -1)" class="w-6 h-6 rounded bg-gray-200 hover:bg-gray-300 text-sm font-bold flex items-center justify-center">-</button>
                            <span class="font-bold text-sm w-8 text-center" x-text="item.qty"></span>
                            <button @click="changeQty(idx, 1)" class="w-6 h-6 rounded bg-gray-200 hover:bg-gray-300 text-sm font-bold flex items-center justify-center">+</button>
                        </div>
                    </div>
                    <div class="text-right flex flex-col items-end justify-between">
                        <button @click="removeItem(idx)" class="text-red-400 hover:text-red-600 text-xs">Remove</button>
                        <span class="font-bold text-sm" x-text="'₱' + (item.qty * item.price).toFixed(2)"></span>
                    </div>
                </div>
            </template>
            <div x-show="cart.length === 0" class="text-center py-8 text-gray-300 text-sm">Cart is empty. Tap products to add.</div>
        </div>

        <!-- Cart Footer -->
        <div class="p-4 border-t space-y-2">
            <div class="flex justify-between text-sm"><span class="text-gray-500">Subtotal</span><span x-text="'₱' + cartSubtotal.toFixed(2)"></span></div>
            <div class="flex justify-between text-sm"><span class="text-gray-500">Discount</span><span x-text="'₱' + cartDiscount.toFixed(2)"></span></div>
            <div class="flex justify-between text-xl font-bold border-t pt-2"><span>Total</span><span x-text="'₱' + cartTotal.toFixed(2)"></span></div>
            <button @click="goToCheckout()" :disabled="cart.length === 0"
                    class="w-full py-3 rounded-lg text-white font-bold text-lg mt-2 transition-colors"
                    :class="cart.length > 0 ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-300 cursor-not-allowed'">
                Pay &amp; Complete
            </button>
        </div>
    </div>
</div>

<!-- ═══════════ CHECKOUT SCREEN ═══════════ -->
<div x-show="screen === 'checkout'" id="checkoutScreen" class="flex flex-1 overflow-hidden p-4 gap-4">

    <!-- LEFT: ORDER REVIEW -->
    <div class="flex-1 bg-white rounded-lg shadow flex flex-col min-w-0">
        <div class="p-4 border-b flex items-center justify-between">
            <h2 class="font-bold text-xl">Order Review</h2>
            <button @click="screen = 'pos'" class="text-blue-600 hover:underline text-sm">&larr; Back to POS</button>
        </div>
        <div class="flex-1 overflow-y-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 sticky top-0">
                    <tr>
                        <th class="text-left p-3 font-medium">#</th>
                        <th class="text-left p-3 font-medium">Product</th>
                        <th class="text-center p-3 font-medium">Qty</th>
                        <th class="text-right p-3 font-medium">Price</th>
                        <th class="text-right p-3 font-medium">Discount</th>
                        <th class="text-right p-3 font-medium">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(item, idx) in cart" :key="item.product_id">
                        <tr class="border-t hover:bg-gray-50">
                            <td class="p-3 text-gray-400" x-text="idx + 1"></td>
                            <td class="p-3">
                                <div class="font-medium" x-text="item.product_name"></div>
                                <div class="text-xs text-gray-400" x-text="item.sku || ''"></div>
                            </td>
                            <td class="p-3 text-center font-bold" x-text="item.qty"></td>
                            <td class="p-3 text-right font-mono" x-text="'₱' + item.price.toFixed(2)"></td>
                            <td class="p-3 text-right font-mono text-red-500" x-text="'₱' + (item.discount || 0).toFixed(2)"></td>
                            <td class="p-3 text-right font-mono font-bold" x-text="'₱' + ((item.qty * item.price) - (item.discount || 0)).toFixed(2)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <!-- Summary Bar -->
        <div class="p-4 border-t bg-gray-50">
            <div class="grid grid-cols-3 gap-4 text-center">
                <div>
                    <div class="text-xs text-gray-500 uppercase">Subtotal</div>
                    <div class="text-lg font-bold" x-text="'₱' + cartSubtotal.toFixed(2)"></div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase">Discount</div>
                    <div class="text-lg font-bold text-red-500" x-text="'₱' + cartDiscount.toFixed(2)"></div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase">Grand Total</div>
                    <div class="text-2xl font-bold text-blue-700" x-text="'₱' + cartTotal.toFixed(2)"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: PAYMENT PANEL -->
    <div class="w-96 bg-white rounded-lg shadow flex flex-col flex-shrink-0">
        <!-- Payment Method Tabs -->
        <div class="p-4 border-b">
            <h3 class="font-bold text-lg mb-3">Payment Method</h3>
            <div class="grid grid-cols-2 gap-2">
                <template x-for="m in paymentMethods" :key="m.value">
                    <button @click="paymentMethod = m.value"
                            class="p-3 rounded-lg border-2 text-sm font-medium transition-all text-center"
                            :class="paymentMethod === m.value ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-gray-200 hover:border-gray-300 text-gray-600'">
                        <div class="text-lg mb-0.5" x-text="m.icon"></div>
                        <div x-text="m.label"></div>
                    </button>
                </template>
            </div>
        </div>

        <!-- Amount Input -->
        <div class="p-4 flex-1 flex flex-col">
            <div class="space-y-4 flex-1">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Amount Due</label>
                    <div class="text-3xl font-bold text-blue-700" x-text="'₱' + cartTotal.toFixed(2)"></div>
                </div>

                <div x-show="paymentMethod === 'cash'">
                    <label class="block text-sm font-medium text-gray-600 mb-1">Cash Received</label>
                    <input type="number" x-model.number="amountTendered" step="0.01" min="0"
                           class="w-full p-3 border-2 rounded-lg text-2xl font-bold text-center focus:border-blue-500 focus:outline-none"
                           placeholder="0.00" @input="calculateChange()">
                    <!-- Quick Cash Buttons -->
                    <div class="grid grid-cols-4 gap-2 mt-2">
                        <template x-for="amt in quickCashAmounts" :key="amt">
                            <button @click="amountTendered = amt; calculateChange()"
                                    class="py-2 text-sm font-medium bg-gray-100 rounded hover:bg-gray-200 transition-colors"
                                    x-text="'₱' + amt"></button>
                        </template>
                    </div>
                </div>

                <div x-show="paymentMethod !== 'cash'">
                    <label class="block text-sm font-medium text-gray-600 mb-1">Reference Number (optional)</label>
                    <input type="text" x-model="paymentRef" class="w-full p-3 border rounded-lg text-sm" placeholder="Transaction ref...">
                </div>

                <!-- Change Display -->
                <div x-show="paymentMethod === 'cash' && amountTendered > 0" class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                    <div class="text-sm text-green-600 font-medium">Change</div>
                    <div class="text-3xl font-bold" :class="changeAmount >= 0 ? 'text-green-700' : 'text-red-600'"
                         x-text="'₱' + Math.abs(changeAmount).toFixed(2)"></div>
                    <div x-show="changeAmount < 0" class="text-xs text-red-500 mt-1">Insufficient amount</div>
                </div>
            </div>

            <!-- Proceed Button -->
            <button @click="completeSale()" :disabled="!canProceed"
                    class="w-full py-4 rounded-lg text-white font-bold text-xl mt-4 transition-colors"
                    :class="canProceed ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-300 cursor-not-allowed'">
                Proceed
            </button>
        </div>
    </div>
</div>

<!-- SUCCESS MODAL -->
<div x-show="showReceipt" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="closeReceipt()">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 text-center">
        <div class="text-5xl mb-3">&#10003;</div>
        <h2 class="text-2xl font-bold text-green-700 mb-2">Sale Complete!</h2>
        <div class="text-gray-600 mb-4">
            <div>Sale #: <span class="font-mono font-bold" x-text="lastSale?.sale_number"></span></div>
            <div class="text-2xl font-bold mt-2">Total: <span x-text="'₱' + parseFloat(lastSale?.total || 0).toFixed(2)"></span></div>
            <div x-show="paymentMethod === 'cash'" class="text-xl mt-1">
                Change: <span class="text-green-600 font-bold" x-text="'₱' + parseFloat(lastSale?.change_due || 0).toFixed(2)"></span>
            </div>
        </div>
        <button @click="closeReceipt()" class="px-8 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
            New Transaction
        </button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function posApp() {
    return {
        screen: 'pos',
        activeShift: null,
        products: [],
        categories: [],
        filteredProducts: [],
        searchQuery: '',
        selectedCategory: '',
        cart: [],
        paymentMethod: 'cash',
        amountTendered: 0,
        changeAmount: 0,
        paymentRef: '',
        showReceipt: false,
        lastSale: null,

        config: {
            cashierId: {{ auth()->id() ?? 'null' }},
            branchId: {{ auth()->user()?->branch_id ?? 'null' }},
        },

        paymentMethods: [
            { value: 'cash',        label: 'Cash',        icon: '💵' },
            { value: 'debit_card',  label: 'Debit Card',  icon: '💳' },
            { value: 'credit_card', label: 'Credit Card', icon: '💳' },
            { value: 'gcash',       label: 'GCash',       icon: '📱' },
            { value: 'maya',        label: 'Maya',        icon: '📱' },
            { value: 'palawanpay',  label: 'PalawanPay',  icon: '📱' },
        ],

        get cartSubtotal() {
            return this.cart.reduce((sum, i) => sum + (i.qty * i.price), 0);
        },
        get cartDiscount() {
            return this.cart.reduce((sum, i) => sum + (i.discount || 0), 0);
        },
        get cartTotal() {
            return this.cartSubtotal - this.cartDiscount;
        },
        get quickCashAmounts() {
            const total = this.cartTotal;
            const amounts = [50, 100, 200, 500, 1000, 2000];
            const rounded = Math.ceil(total / 100) * 100;
            if (rounded > 0 && !amounts.includes(rounded)) amounts.push(rounded);
            if (total > 0 && !amounts.includes(Math.ceil(total))) amounts.push(Math.ceil(total));
            return [...new Set(amounts)].filter(a => a >= total).sort((a, b) => a - b).slice(0, 8);
        },
        get canProceed() {
            if (this.cart.length === 0) return false;
            if (this.paymentMethod === 'cash') {
                return this.amountTendered >= this.cartTotal;
            }
            return true;
        },

        init() {
            this.loadShift();
            this.loadProducts();
        },

        csrfHeader() {
            return {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            };
        },

        async loadShift() {
            try {
                const res = await fetch('/api/pos/shift/current');
                const data = await res.json();
                this.activeShift = (data.success && data.shift) ? data.shift : null;
            } catch { this.activeShift = null; }
        },

        async loadProducts() {
            try {
                const res = await fetch('/api/pos/products/all?branch_id=' + (this.config.branchId || ''));
                const data = await res.json();
                this.products = data.products || [];
                this.categories = data.categories || [];
                this.filterProducts();
            } catch { this.products = []; }
        },

        filterProducts() {
            let result = this.products;
            if (this.selectedCategory) {
                result = result.filter(p => p.category_id == this.selectedCategory);
            }
            if (this.searchQuery.trim()) {
                const q = this.searchQuery.toLowerCase();
                result = result.filter(p =>
                    p.name.toLowerCase().includes(q) ||
                    (p.sku && p.sku.toLowerCase().includes(q)) ||
                    (p.barcode && p.barcode.includes(q))
                );
            }
            this.filteredProducts = result;
        },

        addToCart(product) {
            if (product.stock <= 0) return;
            const existing = this.cart.find(i => i.product_id === product.id);
            if (existing) {
                if (existing.qty >= product.stock) {
                    alert('Not enough stock. Available: ' + product.stock);
                    return;
                }
                existing.qty++;
            } else {
                this.cart.push({
                    product_id: product.id,
                    product_name: product.name,
                    sku: product.sku,
                    barcode: product.barcode,
                    price: parseFloat(product.price),
                    qty: 1,
                    discount: 0,
                });
            }
        },

        changeQty(idx, delta) {
            const item = this.cart[idx];
            const newQty = item.qty + delta;
            if (newQty <= 0) {
                this.cart.splice(idx, 1);
            } else {
                const product = this.products.find(p => p.id === item.product_id);
                if (product && newQty > product.stock) {
                    alert('Not enough stock. Available: ' + product.stock);
                    return;
                }
                item.qty = newQty;
            }
        },

        removeItem(idx) {
            this.cart.splice(idx, 1);
        },

        goToCheckout() {
            if (this.cart.length === 0) return;
            this.amountTendered = 0;
            this.changeAmount = 0;
            this.paymentMethod = 'cash';
            this.paymentRef = '';
            this.screen = 'checkout';
        },

        calculateChange() {
            this.changeAmount = (this.amountTendered || 0) - this.cartTotal;
        },

        async completeSale() {
            if (!this.canProceed) return;
            if (!this.activeShift) {
                alert('No active shift. Please open a shift first.');
                this.screen = 'pos';
                return;
            }

            const tendered = this.paymentMethod === 'cash'
                ? this.amountTendered
                : this.cartTotal;

            try {
                const res = await fetch('/api/pos/sales', {
                    method: 'POST',
                    headers: this.csrfHeader(),
                    body: JSON.stringify({
                        branch_id: this.config.branchId,
                        shift_id: this.activeShift.id,
                        cashier_id: this.config.cashierId,
                        member_id: null,
                        payment_method: this.paymentMethod,
                        amount_tendered: tendered,
                        items: this.cart,
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    this.lastSale = data.sale;
                    this.showReceipt = true;
                } else {
                    alert(data.message || 'Error completing sale.');
                }
            } catch {
                alert('Network error. Please try again.');
            }
        },

        closeReceipt() {
            this.showReceipt = false;
            this.lastSale = null;
            this.cart = [];
            this.amountTendered = 0;
            this.changeAmount = 0;
            this.screen = 'pos';
            this.loadProducts();
        },

        async openShift() {
            const cash = prompt('Enter opening cash amount:');
            if (cash === null) return;
            const amount = parseFloat(cash);
            if (isNaN(amount) || amount < 0) { alert('Invalid amount.'); return; }

            try {
                const res = await fetch('/api/pos/shift/open', {
                    method: 'POST',
                    headers: this.csrfHeader(),
                    body: JSON.stringify({ opening_cash: amount }),
                });
                const data = await res.json();
                if (data.success) {
                    this.activeShift = data.shift;
                    alert('Shift opened!');
                } else {
                    alert(data.message || 'Failed to open shift.');
                }
            } catch { alert('Network error.'); }
        },

        async doCloseShift() {
            const cash = prompt('Enter closing cash amount:');
            if (cash === null) return;
            const amount = parseFloat(cash);
            if (isNaN(amount) || amount < 0) { alert('Invalid amount.'); return; }

            try {
                const res = await fetch('/api/pos/shift/close', {
                    method: 'POST',
                    headers: this.csrfHeader(),
                    body: JSON.stringify({ closing_cash: amount }),
                });
                const data = await res.json();
                if (data.success) {
                    const s = data.shift;
                    const v = parseFloat(s.cash_variance);
                    alert(
                        'Shift closed!\n' +
                        'Sales: ₱' + parseFloat(s.system_sales_total).toFixed(2) + '\n' +
                        'Expected: ₱' + (parseFloat(s.opening_cash) + parseFloat(s.system_sales_total)).toFixed(2) + '\n' +
                        'Closing: ₱' + parseFloat(s.closing_cash).toFixed(2) + '\n' +
                        'Variance: ' + (v >= 0 ? '+' : '') + '₱' + v.toFixed(2)
                    );
                    this.activeShift = null;
                    this.cart = [];
                } else {
                    alert(data.message || 'Failed to close shift.');
                }
            } catch { alert('Network error.'); }
        },
    };
}
</script>

</body>
</html>
