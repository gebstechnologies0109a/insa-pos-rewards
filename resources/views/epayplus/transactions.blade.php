@extends('layouts.epayplus')

@section('title', 'Transactions')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Transactions</h2>
            <small class="text-muted">{{ $transactions->total() }} total transactions</small>
        </div>
        <a href="{{ route('epayplus.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    {{-- Filters --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('epayplus.transactions') }}" class="row g-2 align-items-end">
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
                    <label class="form-label small mb-0">Date</label>
                    <input type="date" name="date" class="form-control form-control-sm" value="{{ request('date') }}">
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
                            <th>Amount</th>
                            <th>Commission</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $txn)
                        <tr>
                            <td><code class="small">{{ $txn->reference_number }}</code></td>
                            <td><small>{{ $txn->retailer?->business_name ?? 'N/A' }}</small></td>
                            <td>
                                @php
                                    $typeBadge = match($txn->type) {
                                        'ELOAD' => 'bg-success',
                                        'BILLS' => 'bg-primary',
                                        'ECASH' => 'bg-danger',
                                        'WIFI' => 'bg-info',
                                        default => 'bg-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $typeBadge }}">{{ $txn->type }}</span>
                            </td>
                            <td><small>{{ $txn->product_name }}</small></td>
                            <td><small>{{ $txn->target_number }}</small></td>
                            <td class="fw-medium">₱{{ number_format($txn->amount, 2) }}</td>
                            <td class="text-primary">₱{{ number_format($txn->commission, 2) }}</td>
                            <td>
                                @php
                                    $statusBadge = match($txn->status) {
                                        'SUCCESS' => 'text-bg-success',
                                        'FAILED' => 'text-bg-danger',
                                        'PROCESSING' => 'text-bg-warning',
                                        default => 'text-bg-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $statusBadge }}">{{ $txn->status }}</span>
                            </td>
                            <td><small class="text-muted">{{ $txn->created_at->format('M d, Y H:i') }}</small></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No transactions found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($transactions->hasPages())
        <div class="card-footer bg-transparent border-0">
            {{ $transactions->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
