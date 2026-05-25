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
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        @keyframes pulse-green { 0%,100%{ box-shadow:0 0 0 0 rgba(16,185,129,.4) } 50%{ box-shadow:0 0 0 6px rgba(16,185,129,0) } }
        @keyframes pulse-yellow { 0%,100%{ box-shadow:0 0 0 0 rgba(245,158,11,.4) } 50%{ box-shadow:0 0 0 6px rgba(245,158,11,0) } }
        .status-dot.online { background: #10b981; animation: pulse-green 2s infinite; }
        .status-dot.syncing { background: #f59e0b; animation: pulse-yellow 1s infinite; }
        .status-dot.offline { background: #ef4444; }
        .buddy-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        .buddy-dot.connected { background: #10b981; animation: pulse-green 2s infinite; }
        .buddy-dot.disconnected { background: #ef4444; }
        .toast-enter { animation: toastIn .3s ease-out; }
        .toast-leave { animation: toastOut .3s ease-in forwards; }
        @keyframes toastIn { from { transform: translateY(-20px); opacity:0 } to { transform:translateY(0); opacity:1 } }
        @keyframes toastOut { from { opacity:1 } to { opacity:0; transform:translateY(-20px) } }
    </style>
    <script src="https://unpkg.com/dexie@4/dist/dexie.min.js"></script>
    <script src="{{ asset('js/db.js') }}"></script>
    <script src="{{ asset('js/insabuddy.js') }}"></script>
    <script src="{{ asset('js/sync-engine.js') }}"></script>
</head>
<body class="bg-gray-100 h-screen flex flex-col overflow-hidden" x-data="posApp()" x-cloak>

<!-- TOAST NOTIFICATIONS -->
<div class="fixed top-4 right-4 z-[100] space-y-2">
    <template x-for="(toast, i) in toasts" :key="toast.id">
        <div class="toast-enter flex items-center gap-2 px-4 py-2.5 rounded-lg shadow-lg text-sm font-medium max-w-xs"
             :class="{
                 'bg-green-600 text-white': toast.type === 'success',
                 'bg-red-600 text-white': toast.type === 'error',
                 'bg-yellow-500 text-white': toast.type === 'warning',
                 'bg-blue-600 text-white': toast.type === 'info',
             }">
            <span x-text="toast.message"></span>
        </div>
    </template>
</div>

<!-- HEADER -->
<header class="bg-white shadow px-4 py-2 flex items-center justify-between flex-shrink-0">
    <h1 class="text-lg font-bold text-gray-800">INSA POS</h1>
    <div class="flex items-center gap-3 text-sm text-gray-600">
        <!-- Sync Status Indicator -->
        <div class="flex items-center gap-1.5 px-2 py-1 rounded-full border cursor-pointer"
             :class="{
                 'border-green-200 bg-green-50': syncStatus === 'synced',
                 'border-yellow-200 bg-yellow-50': syncStatus === 'syncing' || syncStatus === 'pushing' || syncStatus === 'pulling-products' || syncStatus === 'pulling-customers',
                 'border-red-200 bg-red-50': syncStatus === 'offline',
                 'border-gray-200 bg-gray-50': syncStatus === 'partial' || syncStatus === 'error',
             }"
             @click="manualSync()"
             :title="syncStatusTitle">
            <span class="status-dot"
                  :class="{
                      'online': syncStatus === 'synced',
                      'syncing': syncStatus === 'syncing' || syncStatus === 'pushing' || syncStatus === 'pulling-products' || syncStatus === 'pulling-customers',
                      'offline': syncStatus === 'offline' || syncStatus === 'error',
                  }"></span>
            <span class="text-xs font-medium"
                  :class="{
                      'text-green-700': syncStatus === 'synced',
                      'text-yellow-700': syncStatus === 'syncing' || syncStatus === 'pushing' || syncStatus === 'pulling-products' || syncStatus === 'pulling-customers',
                      'text-red-700': syncStatus === 'offline',
                      'text-gray-500': syncStatus === 'partial' || syncStatus === 'error',
                  }"
                  x-text="syncLabel"></span>
            <span x-show="pendingSyncCount > 0"
                  class="ml-1 px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-yellow-200 text-yellow-800"
                  x-text="pendingSyncCount + ' pending'"></span>
        </div>

        <!-- INSABuddy Status -->
        <div class="flex items-center gap-1.5 px-2 py-1 rounded-full border"
             :class="buddyConnected ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50'"
             :title="buddyConnected ? 'INSABuddy connected — hardware features enabled' : 'INSABuddy not detected'">
            <span class="buddy-dot" :class="buddyConnected ? 'connected' : 'disconnected'"></span>
            <span class="text-xs font-medium" :class="buddyConnected ? 'text-green-700' : 'text-gray-400'"
                  x-text="buddyConnected ? 'INSABuddy' : 'No Buddy'"></span>
        </div>
        <template x-if="buddyConnected">
            <div class="flex items-center gap-1">
                <button @click="buddyScanBarcode()" class="p-1.5 rounded hover:bg-gray-100" title="Scan Barcode">
                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                </button>
                <button @click="buddyOpenDrawer()" class="p-1.5 rounded hover:bg-gray-100" title="Open Cash Drawer">
                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </button>
            </div>
        </template>
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
        <div class="flex items-center gap-2">
            <button @click="generateXReading()" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">X-Reading</button>
            <button @click="generateZReading()" class="px-4 py-2 bg-orange-600 text-white rounded-lg text-sm font-medium hover:bg-orange-700">Z-Reading</button>
            <button @click="doCloseShift()" class="px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700">Close Shift</button>
        </div>
    </div>
</div>

<!-- MAIN POS SCREEN -->
<div x-show="screen === 'pos'" id="posScreen" class="flex flex-1 overflow-hidden p-4 gap-4" :class="!activeShift && 'opacity-40 pointer-events-none'">

    <!-- LEFT: PRODUCTS -->
    <div class="flex-1 flex flex-col min-w-0">
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

<!-- CHECKOUT SCREEN -->
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

                <div x-show="paymentMethod === 'cash' && amountTendered > 0" class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                    <div class="text-sm text-green-600 font-medium">Change</div>
                    <div class="text-3xl font-bold" :class="changeAmount >= 0 ? 'text-green-700' : 'text-red-600'"
                         x-text="'₱' + Math.abs(changeAmount).toFixed(2)"></div>
                    <div x-show="changeAmount < 0" class="text-xs text-red-500 mt-1">Insufficient amount</div>
                </div>
            </div>

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
            <div>Sale #: <span class="font-mono font-bold" x-text="lastSale?.sale_number || lastSale?.local_id?.substring(0,8)"></span></div>
            <div class="text-2xl font-bold mt-2">Total: <span x-text="'₱' + parseFloat(lastSale?.total || 0).toFixed(2)"></span></div>
            <div x-show="paymentMethod === 'cash'" class="text-xl mt-1">
                Change: <span class="text-green-600 font-bold" x-text="'₱' + parseFloat(lastSale?.change_due || 0).toFixed(2)"></span>
            </div>
            <div x-show="lastSale?.offline" class="mt-2 text-xs text-yellow-600 bg-yellow-50 border border-yellow-200 rounded px-3 py-1 inline-block">
                Saved offline — will sync when connected
            </div>
        </div>
        <div class="flex gap-3 justify-center">
            <template x-if="buddyConnected">
                <button @click="buddyPrintReceipt()" class="px-6 py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Print Receipt
                </button>
            </template>
            <button @click="closeReceipt()" class="px-8 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                New Transaction
            </button>
        </div>
    </div>
</div>

<!-- CONFLICT RESOLUTION MODAL -->
<div x-show="showConflictModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6">
        <h2 class="text-xl font-bold text-yellow-700 mb-4 flex items-center gap-2">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
            Sync Conflict Detected
        </h2>
        <p class="text-sm text-gray-600 mb-4">The server has newer data that conflicts with your local transaction. Please review:</p>
        <div class="space-y-3 mb-6 max-h-64 overflow-y-auto">
            <template x-for="c in conflictItems" :key="c.product_id + c.field">
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                    <div class="font-medium text-sm" x-text="c.product_name"></div>
                    <div class="text-xs text-gray-500 mt-1">
                        <span x-text="c.field === 'price' ? 'Price' : c.field"></span> changed:
                        <span class="text-red-600 line-through" x-text="'₱' + parseFloat(c.local_value).toFixed(2)"></span>
                        &rarr;
                        <span class="text-green-600 font-bold" x-text="'₱' + parseFloat(c.server_value).toFixed(2)"></span>
                    </div>
                </div>
            </template>
        </div>
        <div class="flex gap-3">
            <button @click="resolveConflict('server')" class="flex-1 py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">
                Use Server Values
            </button>
            <button @click="resolveConflict('local')" class="flex-1 py-3 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300">
                Keep Local Values
            </button>
        </div>
    </div>
</div>

<!-- X/Z READING RESULT MODAL -->
<div x-show="showReadingModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" x-transition>
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
        <h2 class="text-xl font-bold mb-1 flex items-center gap-2"
            :class="readingData?.type === 'z' ? 'text-orange-700' : 'text-blue-700'">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            <span x-text="readingData?.type === 'z' ? 'Z-Reading #' + readingData.z_count : 'X-Reading'"></span>
        </h2>
        <p class="text-xs text-gray-500 mb-4" x-text="readingData?.generated_at"></p>

        <div class="space-y-2 text-sm">
            <div class="flex justify-between py-2 border-b"><span class="text-gray-600">Total Sales</span><span class="font-bold text-lg" x-text="'₱' + parseFloat(readingData?.total_sales || 0).toFixed(2)"></span></div>
            <div class="flex justify-between py-1 border-b"><span class="text-gray-600">Transactions</span><span class="font-semibold" x-text="readingData?.transaction_count || 0"></span></div>
            <div class="flex justify-between py-1 border-b"><span class="text-gray-600">Discounts</span><span class="font-semibold" x-text="'₱' + parseFloat(readingData?.discount_total || 0).toFixed(2)"></span></div>
            <div class="flex justify-between py-1 border-b"><span class="text-gray-600">Voids</span><span class="font-semibold" x-text="'₱' + parseFloat(readingData?.void_total || 0).toFixed(2)"></span></div>
        </div>

        <template x-if="readingData?.payment_breakdown">
            <div class="mt-4">
                <div class="text-xs font-bold text-gray-500 uppercase mb-2">Payment Breakdown</div>
                <div class="grid grid-cols-2 gap-1 text-sm">
                    <template x-for="[method, amount] in Object.entries(readingData.payment_breakdown || {})" :key="method">
                        <template x-if="amount > 0">
                            <div class="flex justify-between col-span-2 py-1 px-2 rounded" :class="method === 'cash' ? 'bg-green-50' : 'bg-gray-50'">
                                <span class="text-gray-600 capitalize" x-text="method.replace('_', ' ')"></span>
                                <span class="font-medium" x-text="'₱' + parseFloat(amount).toFixed(2)"></span>
                            </div>
                        </template>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="readingData?.type === 'z'">
            <div class="mt-3 p-2 bg-orange-50 border border-orange-200 rounded text-xs text-orange-700">
                Totals have been reset. Z-Count: <strong x-text="readingData.z_count"></strong>
            </div>
        </template>

        <div class="mt-5 flex gap-3">
            <button @click="printReading()" x-show="buddyConnected" class="flex-1 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                Print
            </button>
            <button @click="showReadingModal = false" class="flex-1 py-3 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300">
                Close
            </button>
        </div>
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
        buddyConnected: false,
        toasts: [],
        _toastId: 0,

        // Sync state
        syncStatus: 'offline',
        pendingSyncCount: 0,
        showConflictModal: false,
        conflictItems: [],
        conflictLocalId: null,

        // X/Z Reading state
        showReadingModal: false,
        readingData: null,

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
        get syncLabel() {
            const map = {
                'synced': 'Synced',
                'syncing': 'Syncing...',
                'pushing': 'Uploading...',
                'pulling-products': 'Updating...',
                'pulling-customers': 'Updating...',
                'offline': 'Offline',
                'partial': 'Pending',
                'error': 'Sync Error',
            };
            return map[this.syncStatus] || 'Unknown';
        },
        get syncStatusTitle() {
            if (this.syncStatus === 'synced') return 'All data synced. Click to sync now.';
            if (this.syncStatus === 'offline') return 'No server connection. Sales are saved locally.';
            if (this.pendingSyncCount > 0) return this.pendingSyncCount + ' transactions waiting to sync.';
            return 'Click to sync now.';
        },

        // ── Lifecycle ─────────────────────────────────────

        async init() {
            await this.initOffline();
            this.loadShift();
            this.initBuddy();
        },

        async initOffline() {
            const db = window.INSADB;
            if (db) {
                await db.init();
                this.pendingSyncCount = await db.transactions.pendingCount();
            }

            await this.loadProducts();

            if (window.SyncEngine) {
                SyncEngine.on('syncStatus', (status) => { this.syncStatus = status; });
                SyncEngine.on('connectivity', (online) => {
                    if (!online) this.syncStatus = 'offline';
                });
                SyncEngine.on('transactionSynced', (data) => {
                    this.pendingSyncCount = Math.max(0, this.pendingSyncCount - 1);
                    this.showToast('Transaction synced', 'success');
                });
                SyncEngine.on('syncComplete', async (data) => {
                    this.pendingSyncCount = data.pendingCount;
                });
                SyncEngine.on('conflict', (data) => {
                    this.conflictItems = data.conflict;
                    this.conflictLocalId = data.local_id;
                    this.showConflictModal = true;
                });
                SyncEngine.on('productsUpdated', (count) => {
                    if (count > 0) this.refreshProductsFromDB();
                });
                SyncEngine.on('buddyRecovered', (localId) => {
                    this.showToast('Recovered offline data from INSABuddy', 'info');
                });
                SyncEngine.on('syncError', (data) => {
                    this.showToast('Sync error: ' + (data.error || 'Unknown'), 'error');
                });

                SyncEngine.init({
                    branchId: this.config.branchId,
                });
            }
        },

        // ── Toast Notifications ───────────────────────────

        showToast(message, type = 'info', duration = 3000) {
            const id = ++this._toastId;
            this.toasts.push({ id, message, type });
            setTimeout(() => {
                this.toasts = this.toasts.filter(t => t.id !== id);
            }, duration);
        },

        // ── INSABuddy ────────────────────────────────────

        initBuddy() {
            if (typeof INSABuddy === 'undefined') return;
            INSABuddy.startPolling(5000, (connected) => {
                this.buddyConnected = connected;
            });
        },

        async buddyScanBarcode() {
            if (!this.buddyConnected) return;
            const result = await INSABuddy.scan();
            if (result && result.success && result.value) {
                const product = this.products.find(p =>
                    p.barcode === result.value || p.sku === result.value
                );
                if (product) {
                    this.addToCart(product);
                } else {
                    this.searchQuery = result.value;
                    this.filterProducts();
                }
            }
        },

        async buddyOpenDrawer() {
            if (!this.buddyConnected) return;
            await INSABuddy.openDrawer();
        },

        async buddyPrintReceipt() {
            if (!this.buddyConnected || !this.lastSale) return;
            await INSABuddy.printReceipt({
                storeName: 'INSA POS',
                branchName: '{{ auth()->user()->branch?->name ?? "" }}',
                saleNumber: this.lastSale.sale_number || this.lastSale.local_id?.substring(0, 8),
                date: new Date().toLocaleString(),
                cashier: '{{ auth()->user()->name }}',
                items: (this.lastSale._cart || this.cart).map(i => ({ name: i.product_name, qty: i.qty, price: i.price })),
                subtotal: this.cartSubtotal,
                discount: this.cartDiscount,
                total: parseFloat(this.lastSale.total),
                paymentMethod: this.paymentMethod,
                amountTendered: parseFloat(this.lastSale.amount_tendered || 0),
                change: parseFloat(this.lastSale.change_due || 0),
            });
        },

        // ── Sync Actions ─────────────────────────────────

        async manualSync() {
            if (window.SyncEngine) {
                this.showToast('Syncing...', 'info', 2000);
                await SyncEngine.syncNow();
                await this.refreshProductsFromDB();
            }
        },

        async resolveConflict(choice) {
            this.showConflictModal = false;
            const db = window.INSADB;
            if (!db || !this.conflictLocalId) return;

            if (choice === 'server') {
                const tx = await db.transactions.getByLocalId(this.conflictLocalId);
                if (tx && this.conflictItems) {
                    for (const c of this.conflictItems) {
                        const item = tx.items.find(i => i.product_id === c.product_id);
                        if (item && c.field === 'price') {
                            item.price = parseFloat(c.server_value);
                        }
                    }
                    tx.subtotal = tx.items.reduce((s, i) => s + (i.qty * i.price), 0);
                    tx.total = tx.subtotal - tx.items.reduce((s, i) => s + (i.discount || 0), 0);
                    tx.status = 'pending';
                    await db.transactions.add(tx);
                }
            } else {
                const tx = await db.transactions.getByLocalId(this.conflictLocalId);
                if (tx) {
                    tx.status = 'pending';
                    tx.force_local = true;
                    await db.transactions.add(tx);
                }
            }

            this.conflictItems = [];
            this.conflictLocalId = null;
            this.showToast('Conflict resolved — will retry sync', 'success');
        },

        // ── Data Loading ─────────────────────────────────

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
            const db = window.INSADB;

            try {
                const res = await fetch('/api/pos/products/all?branch_id=' + (this.config.branchId || ''));
                const data = await res.json();
                this.products = data.products || [];
                this.categories = data.categories || [];

                if (db && this.products.length > 0) {
                    await db.products.bulkPut(this.products);
                }

                this.filterProducts();
            } catch {
                if (db) {
                    const cached = await db.products.getAll();
                    if (cached.length > 0) {
                        this.products = cached;
                        this.showToast('Using cached products (offline)', 'warning');
                    }
                }
                this.filterProducts();
            }
        },

        async refreshProductsFromDB() {
            const db = window.INSADB;
            if (!db) return;
            const cached = await db.products.getAll();
            if (cached.length > 0) {
                this.products = cached;
                this.filterProducts();
            }
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

        // ── Cart ──────────────────────────────────────────

        addToCart(product) {
            if (product.stock <= 0) return;
            const existing = this.cart.find(i => i.product_id === product.id);
            if (existing) {
                if (existing.qty >= product.stock) {
                    this.showToast('Not enough stock. Available: ' + product.stock, 'warning');
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
                    this.showToast('Not enough stock. Available: ' + product.stock, 'warning');
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

        // ── Complete Sale (offline-first) ─────────────────

        async completeSale() {
            if (!this.canProceed) return;
            if (!this.activeShift) {
                this.showToast('No active shift. Please open a shift first.', 'error');
                this.screen = 'pos';
                return;
            }

            const db = window.INSADB;
            const tendered = this.paymentMethod === 'cash'
                ? this.amountTendered
                : this.cartTotal;

            const localId = db ? db.generateUUID() : crypto.randomUUID ? crypto.randomUUID() : Date.now().toString();

            const txData = {
                local_id: localId,
                branch_id: this.config.branchId,
                shift_id: this.activeShift.id,
                cashier_id: this.config.cashierId,
                member_id: null,
                payment_method: this.paymentMethod,
                amount_tendered: tendered,
                items: JSON.parse(JSON.stringify(this.cart)),
                subtotal: this.cartSubtotal,
                discount_total: this.cartDiscount,
                total: this.cartTotal,
                change_due: Math.max(0, tendered - this.cartTotal),
                status: 'pending',
                created_at: new Date().toISOString(),
            };

            // Save to IndexedDB first (offline-first)
            if (db) {
                await db.transactions.add(txData);
                await db.syncQueue.add({ type: 'transaction_push', ref: localId });
                this.pendingSyncCount++;
            }

            // Save receipt locally
            const receiptData = {
                local_tx_id: localId,
                sale_number: null,
                store_name: 'INSA POS',
                branch_name: '{{ auth()->user()->branch?->name ?? "" }}',
                cashier: '{{ auth()->user()->name }}',
                items: txData.items,
                subtotal: txData.subtotal,
                discount: txData.discount_total,
                total: txData.total,
                payment_method: txData.payment_method,
                amount_tendered: txData.amount_tendered,
                change_due: txData.change_due,
            };
            if (db) await db.receipts.add(receiptData);

            // Try immediate server push (non-blocking)
            let serverSale = null;
            try {
                const res = await fetch('/api/pos/sales', {
                    method: 'POST',
                    headers: this.csrfHeader(),
                    body: JSON.stringify({
                        branch_id: txData.branch_id,
                        shift_id: txData.shift_id,
                        cashier_id: txData.cashier_id,
                        member_id: null,
                        payment_method: txData.payment_method,
                        amount_tendered: txData.amount_tendered,
                        items: txData.items,
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    serverSale = data.sale;
                    if (db) {
                        await db.transactions.markSynced(localId, data.sale.id);
                        this.pendingSyncCount = Math.max(0, this.pendingSyncCount - 1);
                    }
                }
            } catch {
                // Offline — transaction is queued and will sync later
            }

            // Build the sale result for the UI
            this.lastSale = serverSale || {
                local_id: localId,
                sale_number: null,
                total: txData.total,
                amount_tendered: txData.amount_tendered,
                change_due: txData.change_due,
                offline: !serverSale,
                _cart: txData.items,
            };

            this.showReceipt = true;

            // Print via INSABuddy immediately (even offline)
            if (this.buddyConnected) {
                this.buddyPrintReceipt();
                if (typeof INSABuddy !== 'undefined' && SyncEngine) {
                    SyncEngine.pushToBuddy(txData, receiptData);
                }
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

        // ── Shift Management ──────────────────────────────

        async openShift() {
            const cash = prompt('Enter opening cash amount:');
            if (cash === null) return;
            const amount = parseFloat(cash);
            if (isNaN(amount) || amount < 0) { this.showToast('Invalid amount.', 'error'); return; }

            try {
                const res = await fetch('/api/pos/shift/open', {
                    method: 'POST',
                    headers: this.csrfHeader(),
                    body: JSON.stringify({ opening_cash: amount }),
                });
                const data = await res.json();
                if (data.success) {
                    this.activeShift = data.shift;
                    this.showToast('Shift opened!', 'success');
                } else {
                    this.showToast(data.message || 'Failed to open shift.', 'error');
                }
            } catch { this.showToast('Network error opening shift.', 'error'); }
        },

        async doCloseShift() {
            const cash = prompt('Enter closing cash amount:');
            if (cash === null) return;
            const amount = parseFloat(cash);
            if (isNaN(amount) || amount < 0) { this.showToast('Invalid amount.', 'error'); return; }

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
                    this.showToast(data.message || 'Failed to close shift.', 'error');
                }
            } catch { this.showToast('Network error closing shift.', 'error'); }
        },

        // ── X/Z Reading ───────────────────────────────────

        async generateXReading() {
            this.showToast('Generating X-Reading...', 'info');
            try {
                const res = await fetch('/api/pos/x-reading', {
                    method: 'POST',
                    headers: this.csrfHeader(),
                });
                const data = await res.json();
                if (data.success) {
                    this.readingData = { ...data.reading, type: 'x' };
                    this.showReadingModal = true;
                    this.showToast('X-Reading generated', 'success');
                } else {
                    this.showToast(data.message || 'Failed to generate X-Reading', 'error');
                }
            } catch { this.showToast('Network error generating X-Reading', 'error'); }
        },

        async generateZReading() {
            if (!confirm('Generate Z-Reading?\n\nThis is an end-of-day reading that will RESET totals.\nZ-Count will be incremented sequentially.\n\nProceed?')) return;
            this.showToast('Generating Z-Reading...', 'info');
            try {
                const res = await fetch('/api/pos/z-reading', {
                    method: 'POST',
                    headers: this.csrfHeader(),
                });
                const data = await res.json();
                if (data.success) {
                    this.readingData = { ...data.reading, type: 'z' };
                    this.showReadingModal = true;
                    this.showToast('Z-Reading #' + data.reading.z_count + ' generated', 'success');
                } else {
                    this.showToast(data.message || 'Failed to generate Z-Reading', 'error');
                }
            } catch { this.showToast('Network error generating Z-Reading', 'error'); }
        },

        async printReading() {
            if (!this.readingData || !this.buddyConnected) return;
            const r = this.readingData;
            const lines = [];
            const div = '================================';
            lines.push('\x1B\x61\x01');
            lines.push(r.type === 'z' ? 'Z - R E A D I N G' : 'X - R E A D I N G');
            lines.push(r.type === 'z' ? 'Z-Count: #' + r.z_count : 'Cashier Snapshot');
            lines.push(div);
            lines.push('\x1B\x61\x00');
            lines.push('Date: ' + r.generated_at);
            lines.push(div);
            lines.push('Total Sales:     ' + parseFloat(r.total_sales).toFixed(2).padStart(14));
            lines.push('Transactions:    ' + String(r.transaction_count).padStart(14));
            lines.push('Discounts:       ' + parseFloat(r.discount_total).toFixed(2).padStart(14));
            lines.push('Voids:           ' + parseFloat(r.void_total).toFixed(2).padStart(14));
            lines.push(div);
            lines.push('PAYMENT BREAKDOWN');
            const pb = r.payment_breakdown || {};
            for (const [m, a] of Object.entries(pb)) {
                if (parseFloat(a) > 0) {
                    const label = m.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
                    lines.push(label.padEnd(18) + parseFloat(a).toFixed(2).padStart(14));
                }
            }
            lines.push(div);
            if (r.type === 'z') lines.push('*** TOTALS RESET ***');
            lines.push('');
            lines.push('');
            try {
                await INSABuddy.printText(lines.join('\n'));
                this.showToast('Reading printed', 'success');
            } catch { this.showToast('Print failed', 'error'); }
        },
    };
}
</script>

</body>
</html>
