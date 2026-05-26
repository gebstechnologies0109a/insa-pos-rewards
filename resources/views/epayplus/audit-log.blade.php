@extends('layouts.epayplus')

@section('title', 'Audit Log')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Audit Log</h4>
        <small class="text-muted">System activity history</small>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <select name="action" class="form-select form-select-sm">
                    <option value="">All Actions</option>
                    @foreach(['retailer_created','retailer_updated','retailer_activated','retailer_deactivated','balance_adjusted','pin_reset','topup_approved','topup_rejected','manual_credit','provider_created','provider_updated','product_created','product_updated','products_imported','transaction_status_updated','announcement_created','announcement_deleted','settings_updated'] as $a)
                    <option value="{{ $a }}" {{ request('action')==$a?'selected':'' }}>{{ str_replace('_', ' ', ucfirst($a)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <input type="date" name="date" class="form-control form-control-sm" value="{{ request('date') }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-success">Filter</button>
                <a href="{{ route('epayplus.audit-log') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
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
                        <th style="width:160px">Date</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td><small class="text-muted">{{ $log->created_at->format('M d, Y H:i:s') }}</small></td>
                        <td><small class="fw-medium">{{ $log->user?->name ?? 'System' }}</small></td>
                        <td><span class="badge bg-secondary">{{ str_replace('_', ' ', $log->action) }}</span></td>
                        <td><small>{{ $log->description }}</small></td>
                        <td><code class="small">{{ $log->ip_address }}</code></td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No audit records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($logs->hasPages())
    <div class="card-footer bg-transparent border-0">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
