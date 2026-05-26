@extends('layouts.epayplus')

@section('title', 'Transaction Detail')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Transaction Detail</h4>
        <small class="text-muted">Ref: {{ $transaction->reference_number }}</small>
    </div>
    <a href="{{ route('epayplus.transactions') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0"><h6 class="fw-bold mb-0">Transaction Info</h6></div>
            <div class="card-body">
                <table class="table table-borderless table-sm mb-0">
                    <tr><td class="text-muted" style="width:40%">Reference #</td><td><code>{{ $transaction->reference_number }}</code></td></tr>
                    <tr><td class="text-muted">External Ref</td><td>{{ $transaction->external_ref ?? 'N/A' }}</td></tr>
                    <tr><td class="text-muted">Type</td><td><span class="badge bg-{{ match($transaction->type) { 'ELOAD'=>'success','BILLS'=>'primary','ECASH'=>'danger','WIFI'=>'info',default=>'secondary' } }}">{{ $transaction->type }}</span></td></tr>
                    <tr><td class="text-muted">Product</td><td>{{ $transaction->product_name }} <code class="small">({{ $transaction->product_code }})</code></td></tr>
                    <tr><td class="text-muted">Provider</td><td>{{ $transaction->provider_code }}</td></tr>
                    <tr><td class="text-muted">Target Number</td><td class="fw-medium">{{ $transaction->target_number }}</td></tr>
                    <tr><td class="text-muted">Status</td><td>
                        @php $sBadge = match($transaction->status) { 'SUCCESS'=>'text-bg-success','FAILED'=>'text-bg-danger','PROCESSING'=>'text-bg-warning',default=>'text-bg-secondary' }; @endphp
                        <span class="badge {{ $sBadge }} fs-6">{{ $transaction->status }}</span>
                    </td></tr>
                    <tr><td class="text-muted">Remarks</td><td>{{ $transaction->remarks ?? 'N/A' }}</td></tr>
                    <tr><td class="text-muted">Created</td><td>{{ $transaction->created_at->format('M d, Y H:i:s') }}</td></tr>
                    <tr><td class="text-muted">Completed</td><td>{{ $transaction->completed_at?->format('M d, Y H:i:s') ?? 'N/A' }}</td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent border-0"><h6 class="fw-bold mb-0">Financial Info</h6></div>
            <div class="card-body">
                <table class="table table-borderless table-sm mb-0">
                    <tr><td class="text-muted" style="width:40%">Amount</td><td class="fw-bold fs-5 text-success">₱{{ number_format($transaction->amount, 2) }}</td></tr>
                    <tr><td class="text-muted">Fee</td><td>₱{{ number_format($transaction->fee, 2) }}</td></tr>
                    <tr><td class="text-muted">Commission</td><td class="text-primary">₱{{ number_format($transaction->commission, 2) }}</td></tr>
                    <tr><td class="text-muted">Retailer Cost</td><td>₱{{ number_format($transaction->retailer_cost, 2) }}</td></tr>
                    <tr><td class="text-muted">Balance Before</td><td>₱{{ number_format($transaction->balance_before, 2) }}</td></tr>
                    <tr><td class="text-muted">Balance After</td><td>₱{{ number_format($transaction->balance_after, 2) }}</td></tr>
                </table>
            </div>
        </div>

        @if($transaction->retailer)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent border-0"><h6 class="fw-bold mb-0">Retailer</h6></div>
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="fw-medium">{{ $transaction->retailer->business_name }}</div>
                        <small class="text-muted">{{ $transaction->retailer->owner_name }} &bull; {{ $transaction->retailer->mobile_number }}</small>
                    </div>
                    <a href="{{ route('epayplus.retailers.show', $transaction->retailer) }}" class="btn btn-sm btn-outline-success">View</a>
                </div>
            </div>
        </div>
        @endif

        {{-- Status Update --}}
        @if(in_array($transaction->status, ['PROCESSING', 'PENDING']))
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning bg-opacity-10 border-0"><h6 class="fw-bold mb-0">Update Status</h6></div>
            <div class="card-body">
                <form method="POST" action="{{ route('epayplus.transactions.update-status', $transaction) }}">
                    @csrf
                    <div class="mb-2">
                        <select name="status" class="form-select form-select-sm" required>
                            <option value="">Select new status...</option>
                            <option value="SUCCESS">SUCCESS</option>
                            <option value="FAILED">FAILED</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="remarks" class="form-control form-control-sm" placeholder="Remarks (optional)">
                    </div>
                    <button class="btn btn-warning btn-sm w-100" onclick="return confirm('Are you sure you want to update this transaction status?')">
                        Update Status
                    </button>
                    <small class="text-muted d-block mt-1">Note: Marking as FAILED will refund the retailer cost.</small>
                </form>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
