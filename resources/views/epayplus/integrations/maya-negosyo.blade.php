@extends('layouts.epayplus')

@section('title', 'Maya Negosyo')

@section('content')
<div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Maya Negosyo Hub</h4>
        <small class="text-muted">Merchant operations — launch Negosyo, Partner Biller, Checkout, and ePayPlus wallets</small>
    </div>
    <a href="{{ $billerAdminUrl }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-plug"></i> Maya Biller API
    </a>
</div>

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($checkoutDemo && $checkoutRef)
    <div class="alert alert-info">
        <strong>Demo checkout</strong> — reference <code>{{ $checkoutRef }}</code>. Add live keys in <code>.env</code> to use Maya Checkout API.
    </div>
@endif

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm border-success">
            <div class="card-body text-center py-5">
                <div class="mb-3">
                    <span class="badge rounded-pill px-3 py-2" style="background:#00B464;color:#fff;font-size:1rem;">
                        Maya Negosyo
                    </span>
                </div>
                <h5 class="fw-bold">Open Maya Negosyo</h5>
                <p class="text-muted small mb-4">
                    On this device (mobile): opens the Negosyo app via <code>{{ $negosyoDeepLink }}</code>.
                    On desktop: use your phone or install from Play Store.
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="{{ $negosyoDeepLink }}" class="btn btn-success btn-lg px-4 d-md-none">
                        <i class="bi bi-box-arrow-up-right"></i> Open Maya Negosyo
                    </a>
                    <a href="{{ $negosyoPlayStore }}" target="_blank" rel="noopener" class="btn btn-success btn-lg px-4 d-none d-md-inline-flex">
                        <i class="bi bi-google-play"></i> Get Maya Negosyo
                    </a>
                    <a href="{{ $businessPlayStore }}" target="_blank" rel="noopener" class="btn btn-outline-success">
                        Maya Business App
                    </a>
                </div>
                <p class="text-muted small mt-4 mb-0 d-none d-md-block">
                    Scan with your phone: deep link <code>negosyo://</code> or
                    <code>https://www.maya.ph/business/app/*</code>
                </p>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-bold mb-0"><i class="bi bi-activity"></i> Integration status</h6>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        Partner Biller
                        @if($apiConfig['biller_enabled'])
                            <span class="badge bg-success">Enabled</span>
                        @else
                            <span class="badge bg-secondary">Disabled</span>
                        @endif
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        Maya Checkout
                        @if($apiConfig['checkout_enabled'])
                            <span class="badge bg-success">Live</span>
                        @elseif($apiConfig['checkout_demo_mode'])
                            <span class="badge bg-warning text-dark">Demo</span>
                        @else
                            <span class="badge bg-secondary">Off</span>
                        @endif
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        Maya Biller today
                        <span class="fw-semibold">{{ $mayaBillerToday }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        ePayPlus sales today
                        <span class="fw-semibold">₱{{ number_format($todaySales, 2) }}</span>
                    </li>
                </ul>
                <a href="{{ $settlementUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary w-100 mt-3">
                    <i class="bi bi-bank"></i> Settlement reports (pbm.paymaya.com)
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted">E-Load wallet (all retailers)</small>
                <h4 class="fw-bold text-success mb-0">₱{{ number_format($walletStats['total_eload'], 2) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted">Bills / Cash-In wallet</small>
                <h4 class="fw-bold text-primary mb-0">₱{{ number_format($walletStats['total_bills'], 2) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted">Combined balance</small>
                <h4 class="fw-bold mb-0">₱{{ number_format($walletStats['total_combined'], 2) }}</h4>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    @foreach($features as $feature)
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100 {{ $feature['enabled'] ? '' : 'opacity-75' }}">
            <div class="card-body">
                <h6 class="fw-bold"><i class="bi {{ $feature['icon'] }} text-success"></i> {{ $feature['title'] }}</h6>
                <p class="small text-muted mb-2">{{ $feature['desc'] }}</p>
                @if($feature['link'])
                    <a href="{{ $feature['link'] }}" class="small">Open in admin →</a>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-bold mb-0"><i class="bi bi-qr-code"></i> Create Maya Checkout (scaffold)</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('epayplus.integrations.maya-negosyo.checkout') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small">Amount (PHP)</label>
                        <input type="number" name="amount" class="form-control" min="1" step="0.01" value="100" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Description</label>
                        <input type="text" name="description" class="form-control" maxlength="255" value="ePayPlus test payment">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        Create checkout session
                    </button>
                    <p class="text-muted small mt-2 mb-0">
                        Uses demo mode until <code>MAYA_CHECKOUT_PUBLIC_KEY</code> / <code>MAYA_CHECKOUT_SECRET_KEY</code> are set.
                        Webhook: <code>POST {{ url('/api/maya-checkout/webhook') }}</code>
                    </p>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-bold mb-0"><i class="bi bi-clock-history"></i> Recent checkout sessions</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentCheckout as $row)
                            <tr>
                                <td class="font-monospace small">{{ $row->reference_number }}</td>
                                <td>₱{{ number_format($row->amount, 2) }}</td>
                                <td><span class="badge bg-light text-dark">{{ $row->status }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-muted small text-center py-3">No sessions yet</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-transparent border-0">
        <h6 class="fw-bold mb-0"><i class="bi bi-code-slash"></i> App API — <code>GET /api/v2/integrations/maya</code></h6>
    </div>
    <div class="card-body">
        <pre class="small bg-light p-3 rounded mb-0">{{ json_encode($apiConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
</div>
@endsection
