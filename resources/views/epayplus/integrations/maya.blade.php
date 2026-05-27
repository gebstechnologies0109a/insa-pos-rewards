@extends('layouts.epayplus')

@section('title', 'Maya Biller Integration')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Maya Partner Biller</h4>
        <small class="text-muted">Partner Biller API scaffolding — configure via env when Maya onboards</small>
    </div>
    <span class="badge fs-6 {{ $enabled ? 'text-bg-success' : 'text-bg-secondary' }}">
        {{ $enabled ? 'Enabled' : 'Disabled' }}
    </span>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold">Environment</h6>
                <p class="mb-0 text-muted">
                    <code>{{ $environment }}</code>
                    — set <code>MAYA_BILLER_ENVIRONMENT</code> and credentials in <code>.env</code>.
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold">Documentation</h6>
                <p class="mb-0 text-muted">
                    <i class="bi bi-file-earmark-text"></i> Repository: <code>docs/MAYA_BILLER_API.md</code>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-bold">Endpoints for Maya RM</div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>URL</th>
                    <th>Purpose</th>
                </tr>
            </thead>
            <tbody>
                @foreach($endpoints as $url => $label)
                <tr>
                    <td><code class="small">{{ $url }}</code></td>
                    <td>{{ $label }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white small text-muted">
        Inbound requests require headers <code>paymaya-signature</code> and <code>Request-Reference-No</code> when integration is enabled.
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-bold d-flex justify-content-between">
        <span>Recent Maya biller transactions</span>
        <span class="badge text-bg-light text-dark">{{ $transactions->count() }} shown</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>RRN</th>
                    <th>Maya Txn ID</th>
                    <th>State</th>
                    <th>Biller</th>
                    <th>Account</th>
                    <th class="text-end">Amount</th>
                    <th>Callback</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $txn)
                <tr>
                    <td><code class="small">{{ Str::limit($txn->request_reference_no, 20) }}</code></td>
                    <td class="small">{{ $txn->maya_transaction_id ?? '—' }}</td>
                    <td><span class="badge text-bg-primary">{{ $txn->state->value ?? $txn->state }}</span></td>
                    <td>{{ $txn->biller_code }}</td>
                    <td>{{ Str::limit($txn->account_number, 16) }}</td>
                    <td class="text-end">₱{{ number_format($txn->amount, 2) }}</td>
                    <td class="small">{{ $txn->callback_sent_at?->diffForHumans() ?? '—' }}</td>
                    <td class="small text-muted">{{ $txn->created_at->format('M d H:i') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">No Maya biller transactions yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
