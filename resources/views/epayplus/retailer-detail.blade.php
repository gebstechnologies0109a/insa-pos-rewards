@extends('layouts.epayplus')

@section('title', $retailer->business_name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">{{ $retailer->business_name }}</h4>
        <small class="text-muted">Account: {{ $retailer->account_id }}</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('epayplus.retailers.edit', $retailer) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
        <form method="POST" action="{{ route('epayplus.retailers.toggle-status', $retailer) }}" class="d-inline">
            @csrf
            <button class="btn btn-sm {{ $retailer->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                <i class="bi bi-{{ $retailer->is_active ? 'pause' : 'play' }}"></i>
                {{ $retailer->is_active ? 'Deactivate' : 'Activate' }}
            </button>
        </form>
        <a href="{{ route('epayplus.retailers') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<div class="row g-4">
    {{-- Info Card --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-bold mb-0">Retailer Info</h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm mb-0">
                    <tr><td class="text-muted">Owner</td><td class="fw-medium">{{ $retailer->owner_name }}</td></tr>
                    <tr><td class="text-muted">Mobile</td><td>{{ $retailer->mobile_number }}</td></tr>
                    <tr><td class="text-muted">Email</td><td>{{ $retailer->email ?? 'N/A' }}</td></tr>
                    <tr><td class="text-muted">Address</td><td>{{ $retailer->address ?? 'N/A' }}</td></tr>
                    @php $wallets = $retailer->walletBalances(); @endphp
                    <tr><td class="text-muted">E-Load Wallet</td><td class="fw-bold text-success">₱{{ number_format($wallets['eload'], 2) }}</td></tr>
                    <tr><td class="text-muted">Bills / Cash-In Wallet</td><td class="fw-bold text-primary">₱{{ number_format($wallets['bills'], 2) }}</td></tr>
                    <tr><td class="text-muted">Combined Balance</td><td class="fw-bold text-dark fs-5">₱{{ number_format($wallets['combined'], 2) }}</td></tr>
                    <tr><td class="text-muted">Credit Limit</td><td>₱{{ number_format($retailer->credit_limit ?? 0, 2) }}</td></tr>
                    <tr><td class="text-muted">Transactions</td><td>{{ $retailer->transactions_count }}</td></tr>
                    <tr><td class="text-muted">Status</td><td><span class="badge {{ $retailer->is_active ? 'text-bg-success' : 'text-bg-danger' }}">{{ $retailer->is_active ? 'Active' : 'Inactive' }}</span></td></tr>
                    <tr><td class="text-muted">Joined</td><td>{{ $retailer->created_at->format('M d, Y') }}</td></tr>
                    <tr><td class="text-muted">Last Login</td><td>{{ $retailer->last_login_at?->diffForHumans() ?? 'Never' }}</td></tr>
                </table>
            </div>
        </div>

        {{-- Adjust Balance --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-bold mb-0">Adjust Balance</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('epayplus.retailers.adjust-balance', $retailer) }}">
                    @csrf
                    <div class="mb-2">
                        <div class="btn-group btn-group-sm w-100" role="group">
                            <input type="radio" class="btn-check" name="type" value="credit" id="typeCredit" checked>
                            <label class="btn btn-outline-success" for="typeCredit">Credit (+)</label>
                            <input type="radio" class="btn-check" name="type" value="debit" id="typeDebit">
                            <label class="btn btn-outline-danger" for="typeDebit">Debit (-)</label>
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="btn-group btn-group-sm w-100" role="group">
                            <input type="radio" class="btn-check" name="wallet" value="eload" id="walletEload" checked>
                            <label class="btn btn-outline-success" for="walletEload">E-Load</label>
                            <input type="radio" class="btn-check" name="wallet" value="bills" id="walletBills">
                            <label class="btn btn-outline-primary" for="walletBills">Bills / Cash-In</label>
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">₱</span>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required placeholder="Amount">
                        </div>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="reason" class="form-control form-control-sm" required placeholder="Reason for adjustment">
                    </div>
                    <button class="btn btn-success btn-sm w-100">Apply Adjustment</button>
                </form>
            </div>
        </div>

        {{-- Reset PIN --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('epayplus.retailers.reset-pin', $retailer) }}" onsubmit="return confirm('Reset PIN for this retailer?')">
                    @csrf
                    <button class="btn btn-outline-warning btn-sm w-100"><i class="bi bi-key"></i> Reset PIN</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Transactions & Top-ups --}}
    <div class="col-lg-8">
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#txnTab">Transactions ({{ $transactions->count() }})</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#topupTab">Top-ups ({{ $topups->count() }})</a></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="txnTab">
                <div class="card border-0 shadow-sm border-top-0 rounded-top-0">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr><th>Ref #</th><th>Type</th><th>Product</th><th>Target</th><th>Amount</th><th>Status</th><th>Date</th></tr>
                                </thead>
                                <tbody>
                                    @forelse($transactions as $txn)
                                    <tr>
                                        <td><a href="{{ route('epayplus.transactions.show', $txn) }}"><code class="small">{{ $txn->reference_number }}</code></a></td>
                                        <td><span class="badge {{ match($txn->type) { 'ELOAD'=>'bg-success','BILLS'=>'bg-primary','ECASH'=>'bg-danger','WIFI'=>'bg-info',default=>'bg-secondary' } }}">{{ $txn->type }}</span></td>
                                        <td><small>{{ $txn->product_name }}</small></td>
                                        <td><small>{{ $txn->target_number }}</small></td>
                                        <td class="fw-medium">₱{{ number_format($txn->amount, 2) }}</td>
                                        <td><span class="badge {{ match($txn->status) { 'SUCCESS'=>'text-bg-success','FAILED'=>'text-bg-danger','PROCESSING'=>'text-bg-warning',default=>'text-bg-secondary' } }}">{{ $txn->status }}</span></td>
                                        <td><small class="text-muted">{{ $txn->created_at->format('M d H:i') }}</small></td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="7" class="text-center text-muted py-3">No transactions yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="topupTab">
                <div class="card border-0 shadow-sm border-top-0 rounded-top-0">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr><th>Ref #</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr>
                                </thead>
                                <tbody>
                                    @forelse($topups as $tu)
                                    <tr>
                                        <td><code class="small">{{ $tu->reference_number }}</code></td>
                                        <td class="fw-bold">₱{{ number_format($tu->amount, 2) }}</td>
                                        <td><span class="badge bg-secondary">{{ $tu->payment_method }}</span></td>
                                        <td><span class="badge {{ match($tu->status) { 'APPROVED'=>'text-bg-success','REJECTED'=>'text-bg-danger','PENDING'=>'text-bg-warning',default=>'text-bg-secondary' } }}">{{ $tu->status }}</span></td>
                                        <td><small class="text-muted">{{ $tu->created_at->format('M d, Y H:i') }}</small></td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="5" class="text-center text-muted py-3">No top-ups yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
