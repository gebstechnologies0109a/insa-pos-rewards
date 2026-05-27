@extends('layouts.epayplus')

@section('title', 'Maya Biller Integration')

@section('content')
<div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Maya Biller Integration</h4>
        <small class="text-muted">Partner Biller: Validate → Post → Posting Callback → Settlement</small>
    </div>
    <a href="{{ $testingGuideUrl }}" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-clipboard-check"></i> Testing guide
    </a>
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
                <p class="mb-2"><strong>Maya form base URL:</strong>
                    <code class="small">{{ $publicBase }}</code></p>
                <p class="text-muted small mb-0">
                    Enable via <code>MAYA_BILLER_ENABLED=true</code> and configure
                    <code>MAYA_BILLER_SECRET_KEY</code>, <code>MAYA_BILLER_CALLBACK_API_KEY</code>.
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
                <h6 class="fw-bold mb-0"><i class="bi bi-diagram-3"></i> Transaction state legend</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>State</th>
                                <th>Meaning</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stateLegend as $state => $description)
                            <tr>
                                <td><span class="badge bg-light text-dark font-monospace">{{ $state }}</span></td>
                                <td class="small">{{ $description }}</td>
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
                <h6 class="fw-bold mb-0"><i class="bi bi-bank"></i> Settlement reports</h6>
            </div>
            <div class="card-body">
                <p class="small mb-2">
                    Reconcile <strong>FULFILLED</strong> transactions against Maya settlement reports in
                    <a href="{{ $settlementUrl }}" target="_blank" rel="noopener">Maya Business Manager</a>
                    (download settlement files from the portal).
                </p>
                <p class="small text-muted mb-0">
                    Settlement includes bill amount plus contracted fees (service fee, convenience fee) as returned on Validate.
                </p>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-bold mb-0"><i class="bi bi-list-check"></i> Onboarding progress</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small">Saved in this browser (localStorage).</p>
                @php $lastPhase = null; @endphp
                @foreach($onboardingSteps as $step)
                    @if($lastPhase !== $step['phase'])
                        @php $lastPhase = $step['phase']; @endphp
                        <div class="small fw-bold text-uppercase text-muted mt-2 mb-1">{{ $step['phase'] }}</div>
                    @endif
                    <div class="form-check mb-2">
                        <input class="form-check-input maya-onboard-check" type="checkbox"
                               id="onboard-{{ $step['id'] }}" data-step-id="{{ $step['id'] }}">
                        <label class="form-check-label small" for="onboard-{{ $step['id'] }}">{{ $step['label'] }}</label>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-bold mb-0"><i class="bi bi-arrow-left-right"></i> Partner URLs (copy for Maya form)</h6>
            </div>
            <div class="card-body">
                @foreach($endpoints as $ep)
                <div class="mb-3">
                    <label class="form-label small fw-semibold mb-1">{{ $ep['label'] }}</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text font-monospace">{{ $ep['method'] }}</span>
                        <input type="text" class="form-control font-monospace" readonly
                               value="{{ $ep['url'] }}" id="maya-ep-{{ $loop->index }}">
                        <button class="btn btn-outline-primary" type="button"
                                onclick="navigator.clipboard.writeText(document.getElementById('maya-ep-{{ $loop->index }}').value)">
                            Copy
                        </button>
                    </div>
                </div>
                @endforeach
                <p class="small text-muted mb-0">
                    Docs: <code>docs/MAYA_BILLER_INTEGRATION_GUIDE.md</code>,
                    <code>docs/MAYA_BILLER_ONBOARDING_CHECKLIST.md</code>
                </p>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-clock-history"></i> Maya transactions</h6>
                <form method="get" class="d-flex gap-2 align-items-center">
                    <select name="state" class="form-select form-select-sm" style="width: auto;">
                        <option value="">All states</option>
                        @foreach($stateOptions as $option)
                            <option value="{{ $option->value }}" @selected($stateFilter === $option->value)>
                                {{ $option->value }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
                </form>
            </div>
            <div class="card-body p-0">
                @if($transactions->isEmpty())
                    <p class="text-muted p-3 mb-0">No Maya biller transactions match this filter.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Request ref</th>
                                    <th>Biller</th>
                                    <th>Amount</th>
                                    <th>State</th>
                                    <th>Callback</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transactions as $txn)
                                <tr>
                                    <td class="small font-monospace">{{ $txn->request_reference_no }}</td>
                                    <td>{{ $txn->biller_code }}</td>
                                    <td>₱{{ number_format($txn->amount, 2) }}</td>
                                    <td>
                                        <span class="badge @if($txn->state->value === 'FULFILLED') bg-success @elseif(in_array($txn->state->value, ['FAILED','POSTING_FAILED'])) bg-danger @else bg-secondary @endif">
                                            {{ $txn->state->value }}
                                        </span>
                                    </td>
                                    <td class="small text-muted">
                                        {{ $txn->callback_sent_at ? $txn->callback_sent_at->diffForHumans() : '—' }}
                                    </td>
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

@push('scripts')
<script>
(function () {
    const key = 'epayplus_maya_biller_onboarding';
    document.querySelectorAll('.maya-onboard-check').forEach(function (el) {
        try {
            const saved = JSON.parse(localStorage.getItem(key) || '{}');
            if (saved[el.dataset.stepId]) el.checked = true;
        } catch (e) {}
        el.addEventListener('change', function () {
            const data = {};
            document.querySelectorAll('.maya-onboard-check').forEach(function (box) {
                data[box.dataset.stepId] = box.checked;
            });
            localStorage.setItem(key, JSON.stringify(data));
        });
    });
})();
</script>
@endpush
@endsection
