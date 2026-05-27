@extends('layouts.epayplus')
@section('title', 'Device Alerts')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('epayplus.fleet.dashboard') }}">Fleet Dashboard</a></li>
        <li class="breadcrumb-item active">Alerts</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><i class="bi bi-bell text-warning me-2"></i>Device Alerts</h4>
        <p class="text-muted mb-0">Monitor and respond to device alert conditions</p>
    </div>
    <form method="POST" action="{{ route('epayplus.fleet.alerts.bulk-resolve') }}" id="bulkForm">
        @csrf
        <button type="submit" class="btn btn-outline-success" id="bulkResolveBtn" style="display:none;">
            <i class="bi bi-check-all me-1"></i> Resolve Selected
        </button>
    </form>
</div>

{{-- Alert Stats --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-danger">{{ $alertStats['active'] }}</div>
                <small class="text-muted">Active Alerts</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-warning">{{ $alertStats['critical'] }}</div>
                <small class="text-muted">Critical</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-info">{{ $alertStats['today'] }}</div>
                <small class="text-muted">Today's Alerts</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-success">{{ $alertStats['resolved_today'] }}</div>
                <small class="text-muted">Resolved Today</small>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Active</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="acknowledged" {{ request('status') === 'acknowledged' ? 'selected' : '' }}>Acknowledged</option>
                    <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                    <option value="auto_resolved" {{ request('status') === 'auto_resolved' ? 'selected' : '' }}>Auto-Resolved</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="severity" class="form-select">
                    <option value="">All Severity</option>
                    <option value="critical" {{ request('severity') === 'critical' ? 'selected' : '' }}>Critical</option>
                    <option value="warning" {{ request('severity') === 'warning' ? 'selected' : '' }}>Warning</option>
                    <option value="info" {{ request('severity') === 'info' ? 'selected' : '' }}>Info</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select">
                    <option value="">All Types</option>
                    <option value="offline" {{ request('type') === 'offline' ? 'selected' : '' }}>Device Offline</option>
                    <option value="low_battery" {{ request('type') === 'low_battery' ? 'selected' : '' }}>Low Battery</option>
                    <option value="low_balance" {{ request('type') === 'low_balance' ? 'selected' : '' }}>Low Balance</option>
                    <option value="update_failed" {{ request('type') === 'update_failed' ? 'selected' : '' }}>Update Failed</option>
                    <option value="suspicious_activity" {{ request('type') === 'suspicious_activity' ? 'selected' : '' }}>Suspicious Activity</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

{{-- Alerts Table --}}
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 30px;">
                        <input type="checkbox" class="form-check-input" id="selectAll">
                    </th>
                    <th>Severity</th>
                    <th>Device</th>
                    <th>Alert</th>
                    <th>Type</th>
                    <th>Time</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($alerts as $alert)
                <tr>
                    <td>
                        <input type="checkbox" class="form-check-input alert-check" name="alert_ids[]"
                               value="{{ $alert->id }}" form="bulkForm">
                    </td>
                    <td>
                        <span class="badge bg-{{ $alert->severity === 'critical' ? 'danger' : ($alert->severity === 'warning' ? 'warning text-dark' : 'info') }}">
                            {{ strtoupper($alert->severity) }}
                        </span>
                    </td>
                    <td>
                        @if($alert->device)
                            <a href="{{ route('epayplus.fleet.device', $alert->device) }}" class="text-decoration-none fw-medium">
                                {{ $alert->device->name }}
                            </a>
                        @else
                            <span class="text-muted">Unknown</span>
                        @endif
                    </td>
                    <td>
                        <div class="fw-medium">{{ $alert->title }}</div>
                        @if($alert->message)
                            <small class="text-muted">{{ Str::limit($alert->message, 60) }}</small>
                        @endif
                    </td>
                    <td><span class="badge bg-light text-dark">{{ str_replace('_', ' ', $alert->type) }}</span></td>
                    <td><small class="text-muted">{{ $alert->created_at->diffForHumans() }}</small></td>
                    <td class="text-end">
                        @if($alert->status === 'active')
                            <form method="POST" action="{{ route('epayplus.fleet.alert.acknowledge', $alert) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-primary" title="Acknowledge">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('epayplus.fleet.alert.resolve', $alert) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-success" title="Resolve">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </form>
                        @elseif($alert->status === 'acknowledged')
                            <form method="POST" action="{{ route('epayplus.fleet.alert.resolve', $alert) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-success" title="Resolve">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </form>
                        @else
                            <small class="text-muted">
                                {{ $alert->resolved_at?->format('M d H:i') }}
                                @if($alert->resolved_by) by {{ $alert->resolved_by }} @endif
                            </small>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-bell-slash d-block fs-1 mb-2"></i>
                        <h6>No alerts found</h6>
                        <p class="mb-0">All clear! No alerts match your current filters.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($alerts->hasPages())
    <div class="card-footer">{{ $alerts->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.alert-check').forEach(cb => cb.checked = this.checked);
    toggleBulkBtn();
});
document.querySelectorAll('.alert-check').forEach(cb => cb.addEventListener('change', toggleBulkBtn));
function toggleBulkBtn() {
    const checked = document.querySelectorAll('.alert-check:checked').length;
    document.getElementById('bulkResolveBtn').style.display = checked > 0 ? '' : 'none';
}
</script>
@endpush
