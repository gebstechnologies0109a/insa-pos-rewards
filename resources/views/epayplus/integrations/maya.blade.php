@extends('layouts.epayplus')

@section('title', 'Maya Biller Integration')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">Maya Biller Integration</h4>
    <small class="text-muted">Partner Biller inbound API endpoints for Maya app</small>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="bi bi-plug"></i> Status</h6>
                @if($enabled)
                    <span class="badge bg-success">Enabled</span>
                @else
                    <span class="badge bg-secondary">Disabled</span>
                @endif
            </div>
            <div class="card-body">
                <p class="mb-2"><strong>Environment:</strong> {{ $environment }}</p>
                <p class="text-muted small mb-0">
                    Enable via <code>MAYA_BILLER_ENABLED=true</code> and configure
                    <code>MAYA_BILLER_SECRET_KEY</code> in your environment.
                </p>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm border-primary">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-link-45deg"></i> Validate endpoint (Step 1)</h6>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">Register this URL with Maya for Validate Bills Payment:</p>
                <div class="input-group">
                    <input type="text" class="form-control font-monospace small" readonly
                           value="{{ $validateUrl }}" id="mayaValidateUrl">
                    <button class="btn btn-outline-primary" type="button"
                            onclick="navigator.clipboard.writeText(document.getElementById('mayaValidateUrl').value)">
                        Copy
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-bold mb-0"><i class="bi bi-cash-coin"></i> Validate response fees</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small">{{ $feeContractNote }}</p>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <span class="text-muted small d-block">Default convenience fee</span>
                        <strong>₱{{ number_format($defaultFees['convenience_fee'] ?? 0, 2) }}</strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small d-block">Default service fee</span>
                        <strong>₱{{ number_format($defaultFees['service_fee'] ?? 0, 2) }}</strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small d-block">Resolution</span>
                        <span class="small">Override → <code>epay_products.fee</code> → default</span>
                    </div>
                </div>
                @if(count($feeOverrides) > 0)
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Biller override</th><th class="text-end">Convenience</th><th class="text-end">Service</th></tr>
                    </thead>
                    <tbody>
                        @foreach($feeOverrides as $code => $fees)
                        <tr>
                            <td><code>{{ $code }}</code></td>
                            <td class="text-end">₱{{ number_format($fees['convenience_fee'] ?? $fees['convenienceFee'] ?? 0, 2) }}</td>
                            <td class="text-end">₱{{ number_format($fees['service_fee'] ?? $fees['serviceFee'] ?? 0, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
                <p class="small text-muted mb-0 mt-2">
                    Success responses include <code>fees.convenienceFee</code>, <code>fees.serviceFee</code>, and <code>fees.totalFee</code> (see <code>docs/MAYA_BILLER_API.md</code>).
                </p>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-bold mb-0"><i class="bi bi-arrow-left-right"></i> Inbound endpoints</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Method &amp; URL</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($endpoints as $url => $label)
                            <tr>
                                <td class="font-monospace small">{{ $url }}</td>
                                <td>{{ $label }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-bold mb-0"><i class="bi bi-clock-history"></i> Recent Maya transactions</h6>
            </div>
            <div class="card-body p-0">
                @if($transactions->isEmpty())
                    <p class="text-muted p-3 mb-0">No Maya biller transactions yet.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Request ref</th>
                                    <th>Biller</th>
                                    <th>Amount</th>
                                    <th>State</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transactions as $txn)
                                <tr>
                                    <td class="small">{{ $txn->request_reference_no }}</td>
                                    <td>{{ $txn->biller_code }}</td>
                                    <td>₱{{ number_format($txn->amount, 2) }}</td>
                                    <td><span class="badge bg-light text-dark">{{ $txn->state->value ?? $txn->state }}</span></td>
                                    <td class="small text-muted">{{ $txn->updated_at?->diffForHumans() }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
