@extends('layouts.epayplus')

@section('title', 'POS Mode')

@push('styles')
<style>
    :root { --epay-green: #2E7D32; }
    .pos-service-card { border: 2px solid #e8f5e9; transition: .15s; cursor: pointer; }
    .pos-service-card:hover { border-color: var(--epay-green); background: #f1f8e9; }
    .pos-product-card { cursor: pointer; transition: .15s; }
    .pos-product-card:hover { box-shadow: 0 4px 12px rgba(46,125,50,.15); }
    .pos-product-card.in-cart { border-color: var(--epay-green) !important; background: #f1f8e9; }
    #posCart { max-height: calc(100vh - 180px); overflow-y: auto; }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-success">POS Mode</h4>
        <small class="text-muted">ePay Plus services + shop retail items</small>
    </div>
    <form method="get" class="d-flex gap-2 align-items-center">
        <select name="retailer_id" class="form-select form-select-sm" style="min-width:220px" onchange="this.form.submit()">
            @foreach($retailers as $r)
                <option value="{{ $r->id }}" @selected($retailerId == $r->id)>{{ $r->business_name }}</option>
            @endforeach
        </select>
        <a href="{{ route('epayplus.retail-products', ['retailer_id' => $retailerId]) }}" class="btn btn-sm btn-outline-success">Manage Inventory</a>
    </form>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">ePay Plus Services</div>
            <div class="card-body">
                <div class="row g-2">
                    @foreach([
                        ['eload', 'E-Load', 'bi-phone', route('epayplus.products', ['type' => 'ELOAD'])],
                        ['bills', 'Bills Payment', 'bi-receipt', route('epayplus.products', ['type' => 'BILLS'])],
                        ['ecash', 'Cash-in', 'bi-wallet2', route('epayplus.products', ['type' => 'ECASH'])],
                        ['rfid', 'RFID', 'bi-credit-card', route('epayplus.products', ['type' => 'RFID'])],
                    ] as [$key, $label, $icon, $href])
                    <div class="col-6 col-md-3">
                        <a href="{{ $href }}" class="text-decoration-none text-dark">
                            <div class="pos-service-card rounded-3 p-3 text-center h-100">
                                <i class="bi {{ $icon }} fs-3 text-success"></i>
                                <div class="small fw-semibold mt-2">{{ $label }}</div>
                                <div class="text-muted" style="font-size:.7rem">Use app for txn</div>
                            </div>
                        </a>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Shop / Retail</div>
            <div class="card-body">
                <div class="row g-2" id="productGrid">
                    @forelse($retailProducts as $p)
                    <div class="col-6 col-md-4 col-xl-3">
                        <div class="pos-product-card card border h-100" data-id="{{ $p->id }}" data-name="{{ $p->name }}" data-price="{{ $p->price }}" data-stock="{{ $p->stock }}">
                            <div class="card-body p-2">
                                <div class="fw-semibold small">{{ $p->name }}</div>
                                <div class="text-success fw-bold">₱{{ number_format($p->price, 2) }}</div>
                                <div class="text-muted" style="font-size:.75rem">Stock: {{ $p->stock }}</div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <p class="text-muted mb-0">No shop items in stock. <a href="{{ route('epayplus.retail-products', ['retailer_id' => $retailerId]) }}">Add inventory</a>.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm sticky-top" style="top:1rem">
            <div class="card-header bg-success text-white fw-semibold">Cart</div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush" id="posCart"></ul>
            </div>
            <div class="card-footer">
                <div class="d-flex justify-content-between mb-2">
                    <span>Total</span>
                    <strong id="cartTotal">₱0.00</strong>
                </div>
                <button type="button" class="btn btn-success w-100" id="checkoutBtn" disabled>Checkout</button>
                <div id="checkoutMsg" class="small mt-2"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    const cart = new Map();
    const cartEl = document.getElementById('posCart');
    const totalEl = document.getElementById('cartTotal');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const msgEl = document.getElementById('checkoutMsg');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    function renderCart() {
        cartEl.innerHTML = '';
        let total = 0;
        cart.forEach((item, id) => {
            const line = item.qty * item.price;
            total += line;
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center small';
            li.innerHTML = `<span>${item.name} × ${item.qty}</span><span>₱${line.toFixed(2)} <button type="button" class="btn btn-link btn-sm text-danger p-0 ms-1" data-remove="${id}">×</button></span>`;
            cartEl.appendChild(li);
        });
        totalEl.textContent = '₱' + total.toFixed(2);
        checkoutBtn.disabled = cart.size === 0;
        document.querySelectorAll('.pos-product-card').forEach(card => {
            const id = card.dataset.id;
            card.classList.toggle('in-cart', cart.has(id));
        });
    }

    document.getElementById('productGrid')?.addEventListener('click', e => {
        const card = e.target.closest('.pos-product-card');
        if (!card) return;
        const id = card.dataset.id;
        const stock = parseInt(card.dataset.stock, 10);
        const cur = cart.get(id) || { name: card.dataset.name, price: parseFloat(card.dataset.price), qty: 0 };
        if (cur.qty >= stock) { alert('No more stock'); return; }
        cur.qty += 1;
        cart.set(id, cur);
        renderCart();
    });

    cartEl.addEventListener('click', e => {
        const btn = e.target.closest('[data-remove]');
        if (!btn) return;
        cart.delete(btn.dataset.remove);
        renderCart();
    });

    checkoutBtn.addEventListener('click', async () => {
        checkoutBtn.disabled = true;
        msgEl.textContent = 'Processing…';
        msgEl.className = 'small mt-2 text-muted';
        const lines = [];
        cart.forEach((item, id) => {
            lines.push({
                product_type: 'retail',
                product_id: parseInt(id, 10),
                product_name: item.name,
                quantity: item.qty,
                unit_price: item.price
            });
        });
        try {
            const res = await fetch('{{ route('epayplus.pos.checkout') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ lines, payment_method: 'cash', retailer_id: {{ $retailerId }} })
            });
            const data = await res.json();
            if (data.success) {
                msgEl.textContent = 'Sale complete: ' + data.reference + ' — ₱' + data.total.toFixed(2);
                msgEl.className = 'small mt-2 text-success';
                cart.clear();
                renderCart();
                setTimeout(() => location.reload(), 1200);
            } else {
                msgEl.textContent = data.message || 'Checkout failed';
                msgEl.className = 'small mt-2 text-danger';
                checkoutBtn.disabled = false;
            }
        } catch (err) {
            msgEl.textContent = 'Network error';
            msgEl.className = 'small mt-2 text-danger';
            checkoutBtn.disabled = false;
        }
    });
})();
</script>
@endpush
