@extends('layouts.epayplus')
@section('title', 'Kiosk Management')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Kiosk Management</h4>
        <p class="text-muted mb-0">Monitor and manage kiosk devices</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-primary">{{ $stats['total'] }}</div>
                <small class="text-muted">Total Kiosks</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-success">{{ $stats['online'] }}</div>
                <small class="text-muted">Online</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-warning">₱{{ number_format($stats['total_collected'], 2) }}</div>
                <small class="text-muted">Total Collected</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-info">{{ $stats['pending_collection'] }}</div>
                <small class="text-muted">Pending Collection</small>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <form method="GET" class="row g-2">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search kiosks..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="online" {{ request('status') == 'online' ? 'selected' : '' }}>Online</option>
                    <option value="offline" {{ request('status') == 'offline' ? 'selected' : '' }}>Offline</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">Filter</button>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Kiosk</th>
                        <th>Status</th>
                        <th>Location</th>
                        <th>Last Seen</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($kiosks as $kiosk)
                    <tr>
                        <td>
                            <div class="fw-medium">{{ $kiosk->name ?? 'Unnamed Kiosk' }}</div>
                            <small class="text-muted">{{ $kiosk->device_id }}</small>
                        </td>
                        <td>
                            <span class="badge bg-{{ $kiosk->status === 'online' ? 'success' : 'danger' }}">
                                {{ ucfirst($kiosk->status) }}
                            </span>
                            @if(($kiosk->config['locked'] ?? false))
                            <span class="badge bg-dark ms-1"><i class="bi bi-lock-fill"></i></span>
                            @endif
                        </td>
                        <td>{{ $kiosk->location ?? '—' }}</td>
                        <td><small>{{ $kiosk->last_seen_at?->diffForHumans() ?? 'Never' }}</small></td>
                        <td>
                            <a href="{{ route('epayplus.kiosks.show', $kiosk) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-gear"></i> Manage
                            </a>
                            <form method="POST" action="{{ route('epayplus.kiosks.toggle-lock', $kiosk) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-{{ ($kiosk->config['locked'] ?? false) ? 'success' : 'danger' }}">
                                    <i class="bi bi-{{ ($kiosk->config['locked'] ?? false) ? 'unlock' : 'lock' }}"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">No kiosks registered.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($kiosks->hasPages())
    <div class="card-footer bg-white">{{ $kiosks->links() }}</div>
    @endif
</div>
@endsection
