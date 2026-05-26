@extends('layouts.epayplus')

@section('title', 'Top-ups')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Top-up Management</h4>
        <small class="text-muted">{{ $pendingTopups->count() }} pending requests</small>
    </div>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#manualCreditModal">
        <i class="bi bi-plus-lg"></i> Manual Credit
    </button>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item">
        <a class="nav-link {{ $tab=='pending'?'active':'' }}" data-bs-toggle="tab" href="#pendingTab">
            Pending <span class="badge bg-warning text-dark">{{ $pendingTopups->count() }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tab=='history'?'active':'' }}" data-bs-toggle="tab" href="#historyTab">History</a>
    </li>
</ul>

<div class="tab-content">
    {{-- Pending Tab --}}
    <div class="tab-pane fade {{ $tab=='pending'?'show active':'' }}" id="pendingTab">
        @if($pendingTopups->count() > 0)
        <div class="row g-3">
            @foreach($pendingTopups as $topup)
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm border-start border-warning border-3" id="topup-{{ $topup->id }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <div>
                                <div class="fw-bold">{{ $topup->retailer->business_name }}</div>
                                <small class="text-muted">{{ $topup->retailer->owner_name }}</small>
                            </div>
                            <h5 class="fw-bold text-success mb-0">₱{{ number_format($topup->amount, 2) }}</h5>
                        </div>
                        <div class="small text-muted mb-2">
                            <div><i class="bi bi-credit-card"></i> {{ $topup->payment_method }}</div>
                            <div><i class="bi bi-hash"></i> {{ $topup->reference_number }}</div>
                            <div><i class="bi bi-clock"></i> {{ $topup->created_at->diffForHumans() }}</div>
                            @if($topup->proof_url)
                            <div><a href="{{ $topup->proof_url }}" target="_blank"><i class="bi bi-image"></i> View proof</a></div>
                            @endif
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-success btn-sm flex-fill" onclick="processTopup({{ $topup->id }}, 'approve')">
                                <i class="bi bi-check-lg"></i> Approve
                            </button>
                            <button class="btn btn-danger btn-sm flex-fill" onclick="processTopup({{ $topup->id }}, 'reject')">
                                <i class="bi bi-x-lg"></i> Reject
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center text-muted py-5">
            <i class="bi bi-check-circle fs-1 text-success"></i>
            <p class="mt-2">No pending top-up requests.</p>
        </div>
        @endif
    </div>

    {{-- History Tab --}}
    <div class="tab-pane fade {{ $tab=='history'?'show active':'' }}" id="historyTab">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-2">
                <form method="GET" class="row g-2 align-items-end">
                    <input type="hidden" name="tab" value="history">
                    <div class="col-auto">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Status</option>
                            <option value="APPROVED" {{ request('status')=='APPROVED'?'selected':'' }}>Approved</option>
                            <option value="REJECTED" {{ request('status')=='REJECTED'?'selected':'' }}>Rejected</option>
                            <option value="PENDING" {{ request('status')=='PENDING'?'selected':'' }}>Pending</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <select name="retailer_id" class="form-select form-select-sm">
                            <option value="">All Retailers</option>
                            @foreach($retailers as $r)
                            <option value="{{ $r->id }}" {{ request('retailer_id')==$r->id?'selected':'' }}>{{ $r->business_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                    </div>
                    <div class="col-auto">
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-sm btn-success">Filter</button>
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
                                <th class="text-end">Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Approved By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($history as $h)
                            <tr>
                                <td><code class="small">{{ $h->reference_number }}</code></td>
                                <td><small>{{ $h->retailer?->business_name ?? 'N/A' }}</small></td>
                                <td class="text-end fw-bold">₱{{ number_format(abs($h->amount), 2) }}</td>
                                <td><span class="badge bg-secondary">{{ $h->payment_method }}</span></td>
                                <td>
                                    <span class="badge {{ match($h->status) { 'APPROVED'=>'text-bg-success','REJECTED'=>'text-bg-danger','PENDING'=>'text-bg-warning',default=>'text-bg-secondary' } }}">{{ $h->status }}</span>
                                </td>
                                <td><small>{{ $h->approver?->name ?? '-' }}</small></td>
                                <td><small class="text-muted">{{ $h->created_at->format('M d, Y H:i') }}</small></td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No records found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($history->hasPages())
            <div class="card-footer bg-transparent border-0">{{ $history->links() }}</div>
            @endif
        </div>
    </div>
</div>

{{-- Manual Credit Modal --}}
<div class="modal fade" id="manualCreditModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('epayplus.topups.manual-credit') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Manual Credit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Retailer <span class="text-danger">*</span></label>
                        <select name="retailer_id" class="form-select" required>
                            <option value="">Select retailer...</option>
                            @foreach($retailers as $r)
                            <option value="{{ $r->id }}">{{ $r->business_name }} (Balance: ₱{{ number_format($r->balance, 2) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" name="amount" class="form-control" step="0.01" min="1" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <input type="text" name="remarks" class="form-control" placeholder="Reason for manual credit">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Credit Balance</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function processTopup(id, action) {
    if (action === 'reject' && !confirm('Reject this top-up request?')) return;

    fetch(`/epayplus/topups/${id}/${action}`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': window._csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const el = document.getElementById('topup-' + id);
            el.style.opacity = '0.5';
            el.innerHTML = `<div class="card-body text-center text-${action==='approve'?'success':'danger'}">${data.message}</div>`;
            setTimeout(() => el.closest('.col-md-6').remove(), 1500);
        }
    })
    .catch(() => location.reload());
}
</script>
@endpush
