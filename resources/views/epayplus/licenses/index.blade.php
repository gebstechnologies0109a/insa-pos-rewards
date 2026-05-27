@extends('layouts.epayplus')
@section('title', 'License Keys')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">License & Activation</h4>
        <small class="text-muted">Generate, activate, transfer, and revoke machine licenses</small>
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach(['available' => ['Available', 'success'], 'active' => ['Active', 'primary'], 'blocked' => ['Blocked/Revoked', 'danger'], 'expiring' => ['Expiring Soon', 'warning']] as $key => [$label, $color])
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3 text-center">
                <div class="fs-4 fw-bold text-{{ $color }}">{{ $stats[$key] }}</div>
                <small class="text-muted">{{ $label }}</small>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold"><i class="bi bi-plus-circle me-2"></i>Generate Licenses</div>
            <div class="card-body">
                <form method="POST" action="{{ route('epayplus.licenses.generate') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select" required>
                            <option value="retailer">Retailer Device</option>
                            <option value="kiosk">Kiosk Machine</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" class="form-control" value="1" min="1" max="50" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assign Retailer (optional)</label>
                        <select name="retailer_id" class="form-select">
                            <option value="">— None —</option>
                            @foreach($retailers as $r)
                            <option value="{{ $r->id }}">{{ $r->business_name }} ({{ $r->account_id }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expires At (optional)</label>
                        <input type="date" name="expires_at" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <button class="btn btn-success w-100"><i class="bi bi-key me-1"></i> Generate</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-key me-2"></i>License Registry</span>
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Code or machine UID..." value="{{ request('search') }}">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        @foreach(['available','active','revoked','blocked','expired'] as $s)
                        <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-outline-success">Filter</button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Machine UID</th>
                                <th>Retailer</th>
                                <th>Expires</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($licenses as $license)
                            <tr>
                                <td><code class="fw-bold">{{ $license->code }}</code></td>
                                <td><span class="badge bg-secondary">{{ $license->type }}</span></td>
                                <td>
                                    @php $sc = match($license->status) { 'active'=>'success','available'=>'info','blocked','revoked'=>'danger', default=>'warning' }; @endphp
                                    <span class="badge bg-{{ $sc }}">{{ $license->status }}</span>
                                </td>
                                <td><small>{{ $license->machine_uid ?? '—' }}</small></td>
                                <td><small>{{ $license->retailer?->business_name ?? '—' }}</small></td>
                                <td><small>{{ $license->expires_at?->format('M d, Y') ?? '—' }}</small></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        @if($license->status !== 'blocked')
                                        <form method="POST" action="{{ route('epayplus.licenses.block', $license) }}">@csrf
                                            <button class="btn btn-outline-danger" title="Block"><i class="bi bi-slash-circle"></i></button>
                                        </form>
                                        @endif
                                        @if($license->status === 'active')
                                        <form method="POST" action="{{ route('epayplus.licenses.revoke', $license) }}">@csrf
                                            <button class="btn btn-outline-warning" title="Revoke"><i class="bi bi-x-circle"></i></button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No licenses yet. Generate keys above.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($licenses->hasPages())
            <div class="card-footer bg-transparent">{{ $licenses->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
