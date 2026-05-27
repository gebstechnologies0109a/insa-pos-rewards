@extends('layouts.epayplus')

@section('title', 'POS Mode')

@push('styles')
<style>
    :root { --epay-green: #2E7D32; --epay-green-light: #E8F5E9; }
    .pos-layout { min-height: calc(100vh - 120px); }
    .pos-service-card { border: 2px solid var(--epay-green-light); transition: .15s; cursor: pointer; }
    .pos-service-card:hover, .pos-service-card.active { border-color: var(--epay-green); background: #f1f8e9; }
    .pos-tile { border: 1px solid #e0e0e0; border-radius: .75rem; padding: .75rem; cursor: pointer; transition: .15s; background: #fff; }
    .pos-tile:hover { border-color: var(--epay-green); box-shadow: 0 2px 8px rgba(46,125,50,.12); }
    .pos-tile.selected { border-color: var(--epay-green); background: var(--epay-green-light); }
    .pos-catalog { min-height: 320px; }
    .pos-cart-panel { position: sticky; top: 1rem; max-height: calc(100vh - 100px); overflow-y: auto; }
    .pos-breadcrumb .btn-link { color: var(--epay-green); text-decoration: none; }
    .pos-amount-badge { font-weight: 700; color: var(--epay-green); }
    @media (max-width: 991px) {
        .pos-cart-panel { position: static; max-height: none; margin-top: 1rem; }
    }
</style>
@endpush

@section('content')
<div x-data="epayPos()" x-init="init()" class="pos-layout">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0 text-success">POS Mode</h4>
            <small class="text-muted">ePay Plus services — all on this page</small>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <select class="form-select form-select-sm" style="min-width:220px" x-model="retailerId" @change="changeRetailer()">
                @foreach($retailers as $r)
                    <option value="{{ $r->id }}">{{ $r->business_name }}</option>
                @endforeach
            </select>
            <div class="small text-muted" x-show="balances" x-cloak>
                E-Load: <span class="text-success fw-semibold" x-text="formatMoney(balances?.eload)"></span>
                · Bills: <span class="text-success fw-semibold" x-text="formatMoney(balances?.bills)"></span>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm pos-catalog">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="pos-breadcrumb d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-link btn-sm p-0" x-show="view !== 'services'" @click="goBack()">
                            <i class="bi bi-arrow-left"></i> Back
                        </button>
                        <span class="fw-semibold" x-text="breadcrumb"></span>
                    </div>
                    <div x-show="loading" class="spinner-border spinner-border-sm text-success" role="status"></div>
                </div>
                <div class="card-body">
                    {{-- Service picker --}}
                    <template x-if="view === 'services'">
                        <div class="row g-2">
                            <template x-for="svc in services" :key="svc.key">
                                <div class="col-6 col-md-3">
                                    <div class="pos-service-card rounded-3 p-3 text-center h-100" @click="selectService(svc)">
                                        <i class="bi fs-3 text-success" :class="svc.icon"></i>
                                        <div class="small fw-semibold mt-2" x-text="svc.label"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Bills categories --}}
                    <template x-if="view === 'bill-categories'">
                        <div class="row g-2">
                            <template x-for="cat in billCategories" :key="cat">
                                <div class="col-6 col-md-4">
                                    <div class="pos-tile text-center h-100" @click="selectBillCategory(cat)">
                                        <i class="bi bi-receipt text-success fs-4"></i>
                                        <div class="small fw-semibold mt-2" x-text="cat"></div>
                                    </div>
                                </div>
                            </template>
                            <div class="col-12 text-muted small" x-show="!loading && billCategories.length === 0">No bill categories found.</div>
                        </div>
                    </template>

                    {{-- Bills billers --}}
                    <template x-if="view === 'bill-billers'">
                        <div class="row g-2">
                            <template x-for="b in billBillers" :key="b.id">
                                <div class="col-6 col-md-4">
                                    <div class="pos-tile h-100" @click="openAddModal(b, 'BILLS')">
                                        <div class="fw-semibold small" x-text="b.name"></div>
                                        <div class="text-muted" style="font-size:.7rem" x-text="b.providerName"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Providers grid --}}
                    <template x-if="view === 'providers'">
                        <div class="row g-2">
                            <template x-for="p in providers" :key="p.id">
                                <div class="col-6 col-md-4 col-lg-3">
                                    <div class="pos-tile text-center h-100" @click="selectProvider(p)">
                                        <i class="bi bi-building text-success fs-4"></i>
                                        <div class="small fw-semibold mt-2" x-text="p.name"></div>
                                    </div>
                                </div>
                            </template>
                            <div class="col-12 text-muted small" x-show="!loading && providers.length === 0">No providers found.</div>
                        </div>
                    </template>

                    {{-- Products grid --}}
                    <template x-if="view === 'products'">
                        <div class="mb-2 small text-muted" x-show="selectedProvider" x-text="selectedProvider?.name"></div>
                        <div class="row g-2">
                            <template x-for="pr in products" :key="pr.id">
                                <div class="col-6 col-md-4 col-lg-3">
                                    <div class="pos-tile h-100" @click="openAddModal(pr, activeType)">
                                        <div class="small fw-semibold" x-text="pr.name"></div>
                                        <div class="pos-amount-badge small" x-show="pr.amount > 0" x-text="formatMoney(pr.amount)"></div>
                                        <div class="text-muted" style="font-size:.65rem" x-show="pr.productKind === 'promo'">Promo</div>
                                    </div>
                                </div>
                            </template>
                            <div class="col-12 text-muted small" x-show="!loading && products.length === 0">No products for this provider.</div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Cart --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm pos-cart-panel">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-cart3 me-1"></i> Cart</span>
                    <span class="badge bg-light text-success" x-text="cart.length"></span>
                </div>
                <div class="card-body p-0">
                    <div x-show="cart.length === 0" class="p-4 text-center text-muted small">Tap a product to add items</div>
                    <ul class="list-group list-group-flush" x-show="cart.length > 0">
                        <template x-for="(line, idx) in cart" :key="line.uid">
                            <li class="list-group-item small">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="fw-semibold" x-text="line.label"></div>
                                        <div class="text-muted" x-text="line.subtitle"></div>
                                        <div x-show="line.target" class="text-muted"># <span x-text="line.target"></span></div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-semibold text-success" x-text="formatMoney(line.amount)"></div>
                                        <button type="button" class="btn btn-link btn-sm text-danger p-0" @click="removeFromCart(idx)">Remove</button>
                                    </div>
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>
                <div class="card-footer bg-white" x-show="cart.length > 0">
                    <div class="d-flex justify-content-between mb-2 fw-bold">
                        <span>Total</span>
                        <span class="text-success" x-text="formatMoney(cartTotal)"></span>
                    </div>
                    <button type="button" class="btn btn-success w-100" :disabled="checkingOut" @click="checkout()">
                        <span x-show="!checkingOut">Checkout</span>
                        <span x-show="checkingOut">Processing…</span>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-2" @click="clearCart()">Clear cart</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Add to cart modal --}}
    <div class="modal fade" id="posAddModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" x-show="pendingItem">
                <div class="modal-header">
                    <h5 class="modal-title" x-text="pendingItem?.name || 'Add to cart'"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3" x-show="pendingItem?.needsPhone">
                        <label class="form-label">Mobile number</label>
                        <input type="tel" class="form-control" x-model="form.target" placeholder="09XXXXXXXXX" maxlength="13">
                    </div>
                    <div class="mb-3" x-show="pendingItem?.needsAccount">
                        <label class="form-label">Account / card number</label>
                        <input type="text" class="form-control" x-model="form.target" placeholder="Account number">
                    </div>
                    <div class="mb-3" x-show="pendingItem?.needsCustomAmount || pendingItem?.amount <= 0">
                        <label class="form-label">Amount (₱)</label>
                        <input type="number" class="form-control" x-model.number="form.amount" min="1" step="0.01">
                    </div>
                    <div class="alert alert-light small mb-0" x-show="pendingItem && !pendingItem?.needsCustomAmount && pendingItem?.amount > 0">
                        Amount: <strong x-text="formatMoney(pendingItem?.amount)"></strong>
                        <span x-show="pendingItem?.fee > 0"> + fee <span x-text="formatMoney(pendingItem?.fee)"></span></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" @click="confirmAddToCart()">Add to cart</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Result toast area --}}
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index:1080">
        <div x-show="toastMessage" x-transition class="toast show align-items-center text-bg-success border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body" x-text="toastMessage"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" @click="toastMessage = ''"></button>
            </div>
        </div>
        <div x-show="errorMessage" x-transition class="toast show align-items-center text-bg-danger border-0 mt-2" role="alert">
            <div class="d-flex">
                <div class="toast-body" x-text="errorMessage"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" @click="errorMessage = ''"></button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function epayPos() {
    const api = {
        providers: @json(route('epayplus.pos.api.providers')),
        products: @json(route('epayplus.pos.api.products')),
        billCategories: @json(route('epayplus.pos.api.bill-categories')),
        billBillers: @json(route('epayplus.pos.api.bill-billers')),
        balance: @json(route('epayplus.pos.api.balance')),
        checkout: @json(route('epayplus.pos.checkout')),
        posPage: @json(route('epayplus.pos')),
    };

    return {
        retailerId: @json($retailerId),
        services: [
            { key: 'eload', type: 'ELOAD', label: 'E-Load', icon: 'bi-phone' },
            { key: 'bills', type: 'BILLS', label: 'Bills Payment', icon: 'bi-receipt' },
            { key: 'ecash', type: 'ECASH', label: 'Cash-in', icon: 'bi-wallet2' },
            { key: 'rfid', type: 'RFID', label: 'RFID', icon: 'bi-credit-card' },
        ],
        view: 'services',
        activeType: null,
        activeService: null,
        providers: [],
        products: [],
        billCategories: [],
        billBillers: [],
        selectedProvider: null,
        selectedBillCategory: null,
        navStack: [],
        loading: false,
        cart: [],
        pendingItem: null,
        pendingType: null,
        form: { target: '', amount: 0 },
        addModal: null,
        checkingOut: false,
        balances: null,
        toastMessage: '',
        errorMessage: '',

        get breadcrumb() {
            if (this.view === 'services') return 'Select a service';
            if (this.view === 'bill-categories') return 'Bills → Category';
            if (this.view === 'bill-billers') return 'Bills → ' + (this.selectedBillCategory || '');
            if (this.view === 'providers') return (this.activeService?.label || '') + ' → Provider';
            if (this.view === 'products') return (this.activeService?.label || '') + ' → ' + (this.selectedProvider?.name || 'Products');
            return '';
        },

        get cartTotal() {
            return this.cart.reduce((s, l) => s + Number(l.amount || 0), 0);
        },

        init() {
            this.retailerId = String(this.retailerId);
            this.addModal = new bootstrap.Modal(document.getElementById('posAddModal'));
            this.loadBalance();
        },

        async fetchJson(url) {
            const res = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Request failed');
            return data;
        },

        qs(params) {
            const q = new URLSearchParams({ ...params, retailer_id: this.retailerId });
            return q.toString();
        },

        async loadBalance() {
            try {
                const data = await this.fetchJson(api.balance + '?' + this.qs({}));
                this.balances = data.balances;
            } catch (e) { /* optional */ }
        },

        changeRetailer() {
            const url = new URL(api.posPage, window.location.origin);
            url.searchParams.set('retailer_id', this.retailerId);
            window.location.href = url.toString();
        },

        selectService(svc) {
            this.activeService = svc;
            this.activeType = svc.type;
            this.navStack = [{ view: 'services' }];
            if (svc.type === 'BILLS') {
                this.loadBillCategories();
            } else {
                this.loadProviders();
            }
        },

        async loadProviders() {
            this.loading = true;
            this.view = 'providers';
            this.providers = [];
            this.selectedProvider = null;
            try {
                const data = await this.fetchJson(api.providers + '?' + this.qs({ type: this.activeType }));
                this.providers = data.providers || [];
            } catch (e) {
                this.errorMessage = e.message;
            } finally {
                this.loading = false;
            }
        },

        async loadBillCategories() {
            this.loading = true;
            this.view = 'bill-categories';
            this.billCategories = [];
            try {
                const data = await this.fetchJson(api.billCategories + '?' + this.qs({}));
                this.billCategories = data.categories || [];
            } catch (e) {
                this.errorMessage = e.message;
            } finally {
                this.loading = false;
            }
        },

        async selectBillCategory(cat) {
            this.selectedBillCategory = cat;
            this.navStack.push({ view: 'bill-categories', category: cat });
            this.loading = true;
            this.view = 'bill-billers';
            this.billBillers = [];
            try {
                const data = await this.fetchJson(api.billBillers + '?' + this.qs({ category: cat }));
                this.billBillers = data.billers || [];
            } catch (e) {
                this.errorMessage = e.message;
            } finally {
                this.loading = false;
            }
        },

        async selectProvider(p) {
            this.selectedProvider = p;
            this.navStack.push({ view: 'providers', provider: p });
            this.loading = true;
            this.view = 'products';
            this.products = [];
            try {
                const data = await this.fetchJson(api.products + '?' + this.qs({ type: this.activeType, provider_id: p.id }));
                this.products = data.products || [];
            } catch (e) {
                this.errorMessage = e.message;
            } finally {
                this.loading = false;
            }
        },

        goBack() {
            if (this.navStack.length) this.navStack.pop();
            const prev = this.navStack[this.navStack.length - 1];
            if (!prev || prev.view === 'services') {
                this.view = 'services';
                this.activeService = null;
                this.activeType = null;
                this.navStack = [];
                return;
            }
            if (prev.view === 'bill-categories') {
                this.view = 'bill-categories';
                return;
            }
            if (prev.view === 'providers') {
                this.view = 'providers';
                this.selectedProvider = null;
                return;
            }
            this.view = 'services';
        },

        openAddModal(item, type) {
            this.pendingItem = item;
            this.pendingType = type;
            this.form.target = '';
            this.form.amount = item.amount > 0 ? item.amount : '';
            this.addModal.show();
        },

        confirmAddToCart() {
            const item = this.pendingItem;
            const type = this.pendingType;
            const amount = item.needsCustomAmount || item.amount <= 0
                ? Number(this.form.amount)
                : Number(item.amount);
            if (!amount || amount < 0.01) {
                this.errorMessage = 'Enter a valid amount';
                return;
            }
            if ((item.needsPhone || item.needsAccount) && !String(this.form.target || '').trim()) {
                this.errorMessage = item.needsPhone ? 'Mobile number is required' : 'Account number is required';
                return;
            }
            let providerCode = item.providerCode || this.selectedProvider?.code || item.code;
            if (type === 'BILLS') {
                providerCode = item.providerCode || item.code;
            }
            this.cart.push({
                uid: Date.now() + Math.random(),
                type,
                provider_code: providerCode,
                product_code: item.code,
                amount,
                target: String(this.form.target || '').trim(),
                label: item.name,
                subtitle: type + (item.providerName ? ' · ' + item.providerName : ''),
            });
            this.addModal.hide();
            this.pendingItem = null;
            this.toastMessage = 'Added to cart';
            setTimeout(() => { this.toastMessage = ''; }, 2000);
        },

        removeFromCart(idx) {
            this.cart.splice(idx, 1);
        },

        clearCart() {
            this.cart = [];
        },

        async checkout() {
            if (!this.cart.length) return;
            this.checkingOut = true;
            this.errorMessage = '';
            try {
                const res = await fetch(api.checkout, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        retailer_id: this.retailerId,
                        items: this.cart.map(l => ({
                            type: l.type,
                            provider_code: l.provider_code,
                            product_code: l.product_code,
                            amount: l.amount,
                            target: l.target,
                        })),
                    }),
                });
                const data = await res.json();
                if (!data.success && !data.results?.length) {
                    throw new Error(data.message || 'Checkout failed');
                }
                const refs = (data.results || []).map(r => r.referenceNumber).filter(Boolean).join(', ');
                this.toastMessage = data.message + (refs ? ' Ref: ' + refs : '');
                this.cart = [];
                if (data.balances) {
                    this.balances = data.balances;
                } else {
                    await this.loadBalance();
                }
                if (data.errors?.length) {
                    this.errorMessage = data.errors.map(e => e.message).join('; ');
                }
            } catch (e) {
                this.errorMessage = e.message;
            } finally {
                this.checkingOut = false;
            }
        },

        formatMoney(v) {
            const n = Number(v);
            if (Number.isNaN(n)) return '—';
            return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
    };
}
</script>
@endpush
