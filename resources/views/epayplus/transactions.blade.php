@extends('layouts.epayplus')

@section('title', 'Transactions')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Transactions</h4>
        <small class="text-muted">{{ $transactions->total() }} total transactions</small>
    </div>
    <a href="{{ route('epayplus.transactions.export', request()->all()) }}" class="btn btn-outline-success btn-sm">
        <i class="bi bi-download"></i> Export CSV
    </a>
</div>

@if(isset($summary))
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-2 text-center">
                <small class="text-muted">Filtered Volume</small>
                <div class="fw-bold">₱{{ number_format($summary['total_amount'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-2 text-center">
                <small class="text-muted">Earnings (Commission)</small>
                <div class="fw-bold text-primary">₱{{ number_format($summary['total_earnings'], 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-2 text-center">
                <small class="text-muted">Successful Txns</small>
                <div class="fw-bold text-success">{{ number_format($summary['success_count']) }}</div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-0">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Ref #, Target #, External Ref..." value="{{ request('search') }}">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0">Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    @foreach(['ELOAD', 'BILLS', 'ECASH', 'WIFI'] as $type)
                    <option value="{{ $type }}" {{ request('type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    @foreach(['SUCCESS', 'FAILED', 'PROCESSING', 'PENDING'] as $status)
                    <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0">Retailer</label>
                <select name="retailer_id" class="form-select form-select-sm">
                    <option value="">All Retailers</option>
                    @foreach($retailers as $r)
                    <option value="{{ $r->id }}" {{ request('retailer_id') == $r->id ? 'selected' : '' }}>{{ $r->business_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-success">Filter</button>
                <a href="{{ route('epayplus.transactions') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Ref #</th>
                        <th>Retailer</th>
                        <th>Type</th>
                        <th>Product</th>
                        <th>Target</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Commission</th>
                        <th class="text-end">Earnings</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $txn)
                    <tr>
                        <td><a href="{{ route('epayplus.transactions.show', $txn) }}" class="text-decoration-none"><code class="small">{{ $txn->reference_number }}</code></a></td>
                        <td><small>{{ $txn->retailer?->business_name ?? 'N/A' }}</small></td>
                        <td>
                            @php $typeBadge = match($txn->type) { 'ELOAD'=>'bg-success','BILLS'=>'bg-primary','ECASH'=>'bg-danger','WIFI'=>'bg-info',default=>'bg-secondary' }; @endphp
                            <span class="badge {{ $typeBadge }}">{{ $txn->type }}</span>
                        </td>
                        <td><small>{{ $txn->product_name }}</small></td>
                        <td><small>{{ $txn->target_number }}</small></td>
                        <td class="text-end fw-medium">₱{{ number_format($txn->amount, 2) }}</td>
                        <td class="text-end text-primary">₱{{ number_format($txn->commission, 2) }}</td>
                        <td class="text-end fw-medium text-success">₱{{ number_format($txn->status === 'SUCCESS' ? $txn->commission : 0, 2) }}</td>
                        <td>
                            @php $sBadge = match($txn->status) { 'SUCCESS'=>'text-bg-success','FAILED'=>'text-bg-danger','PROCESSING'=>'text-bg-warning',default=>'text-bg-secondary' }; @endphp
                            <span class="badge {{ $sBadge }}">{{ $txn->status }}</span>
                        </td>
                        <td><small class="text-muted">{{ $txn->created_at->format('M d, Y H:i') }}</small></td>
                        <td>
                            <a href="{{ route('epayplus.transactions.show', $txn) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="11" class="text-center text-muted py-4">No transactions found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer bg-transparent border-0">
        {{ $transactions->links() }}
    </div>
    @endif
</div>
@endsection
