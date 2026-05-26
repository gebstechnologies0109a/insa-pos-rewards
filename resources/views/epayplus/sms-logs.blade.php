@extends('layouts.epayplus')
@section('title', 'SMS Gateway')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">SMS Gateway</h4>
        <p class="text-muted mb-0">Monitor SMS messages across all devices</p>
    </div>
    <a href="{{ route('epayplus.sms.templates') }}" class="btn btn-outline-primary">
        <i class="bi bi-file-text me-1"></i> Templates & Config
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-success">{{ $stats['total_sent'] }}</div>
                <small class="text-muted">Total Sent</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-primary">{{ $stats['total_received'] }}</div>
                <small class="text-muted">Total Received</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-danger">{{ $stats['failed'] }}</div>
                <small class="text-muted">Failed</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-info">{{ $stats['today'] }}</div>
                <small class="text-muted">Today</small>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <form method="GET" class="row g-2">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Number or message..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="direction" class="form-select form-select-sm">
                    <option value="">All Directions</option>
                    <option value="incoming" {{ request('direction') == 'incoming' ? 'selected' : '' }}>Incoming</option>
                    <option value="outgoing" {{ request('direction') == 'outgoing' ? 'selected' : '' }}>Outgoing</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="sent" {{ request('status') == 'sent' ? 'selected' : '' }}>Sent</option>
                    <option value="delivered" {{ request('status') == 'delivered' ? 'selected' : '' }}>Delivered</option>
                    <option value="received" {{ request('status') == 'received' ? 'selected' : '' }}>Received</option>
                    <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">Go</button>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Direction</th>
                        <th>Number</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Provider</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td>
                            <i class="bi bi-{{ $log->direction === 'incoming' ? 'arrow-down-left text-primary' : 'arrow-up-right text-success' }}"></i>
                            {{ ucfirst($log->direction) }}
                        </td>
                        <td class="fw-medium">{{ $log->number }}</td>
                        <td>
                            <small class="text-truncate d-inline-block" style="max-width:250px" title="{{ $log->message }}">
                                {{ $log->message }}
                            </small>
                        </td>
                        <td>
                            @php $sc = ['sent'=>'success','delivered'=>'success','received'=>'primary','failed'=>'danger','pending'=>'warning']; @endphp
                            <span class="badge bg-{{ $sc[$log->status] ?? 'secondary' }}">{{ $log->status }}</span>
                        </td>
                        <td><small>{{ $log->provider ?? '—' }}</small></td>
                        <td><small>{{ $log->created_at?->format('M d H:i') }}</small></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No SMS logs found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($logs->hasPages())
    <div class="card-footer bg-white">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
