@extends('layouts.epayplus')
@section('title', 'Device Fleet Dashboard')

@push('styles')
<style>
    .device-card { transition: all .2s ease; border-left: 4px solid transparent; }
    .device-card:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.1) !important; }
    .device-card.status-online { border-left-color: #198754; }
    .device-card.status-warning { border-left-color: #ffc107; }
    .device-card.status-offline { border-left-color: #dc3545; }
    .stat-card { border: none; border-radius: .75rem; }
    .stat-card .stat-icon { width: 48px; height: 48px; border-radius: .5rem; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
    .battery-bar { height: 6px; border-radius: 3px; background: #e9ecef; overflow: hidden; }
    .battery-fill { height: 100%; border-radius: 3px; transition: width .3s; }
    .pulse-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
    .pulse-dot.online { background: #198754; animation: pulse 2s infinite; }
    .pulse-dot.warning { background: #ffc107; }
    .pulse-dot.offline { background: #dc3545; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }
    .filter-bar { background: #fff; border-radius: .75rem; }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">
            <i class="bi bi-hdd-network text-success me-2"></i>Device Fleet Dashboard
        </h4>
        <p class="text-muted mb-0">Real-time monitoring and management of all connected devices</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('epayplus.fleet.alerts') }}" class="btn btn-outline-warning position-relative">
            <i class="bi bi-bell me-1"></i> Alerts
            @if($stats['alerts'] > 0)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $stats['alerts'] }}</span>
            @endif
        </a>
        <a href="{{ route('epayplus.fleet.updates') }}" class="btn btn-outline-primary">
            <i class="bi bi-cloud-arrow-up me-1"></i> OTA Updates
        </a>
        <a href="{{ route('epayplus.fleet.groups') }}" class="btn btn-outline-secondary">
            <i class="bi bi-collection me-1"></i> Groups
        </a>
    </div>
</div>

{{-- Stats Row --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                    <i class="bi bi-phone"></i>
                </div>
                <div>
                    <div class="fs-3 fw-bold">{{ $stats['total'] }}</div>
                    <small class="text-muted">Total Devices</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                    <i class="bi bi-wifi"></i>
                </div>
                <div>
                    <div class="fs-3 fw-bold text-success" id="online-count">{{ $stats['online'] }}</div>
                    <small class="text-muted">Online</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                    <i class="bi bi-wifi-off"></i>
                </div>
                <div>
                    <div class="fs-3 fw-bold text-danger" id="offline-count">{{ $stats['offline'] }}</div>
                    <small class="text-muted">Offline</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div>
                    <div class="fs-3 fw-bold text-warning">{{ $stats['alerts'] }}</div>
                    <small class="text-muted">Active Alerts</small>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card filter-bar shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search devices..."
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="group_id" class="form-select">
                    <option value="">All Groups</option>
                    @foreach($groups as $group)
                        <option value="{{ $group->id }}" {{ request('group_id') == $group->id ? 'selected' : '' }}>
                            {{ $group->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="online" {{ request('status') === 'online' ? 'selected' : '' }}>Online</option>
                    <option value="warning" {{ request('status') === 'warning' ? 'selected' : '' }}>Warning</option>
                    <option value="offline" {{ request('status') === 'offline' ? 'selected' : '' }}>Offline</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="type" class="form-select">
                    <option value="">All Types</option>
                    <option value="retailer" {{ request('type') === 'retailer' ? 'selected' : '' }}>Retailer</option>
                    <option value="kiosk" {{ request('type') === 'kiosk' ? 'selected' : '' }}>Kiosk</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="{{ route('epayplus.fleet.dashboard') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Device Grid --}}
<div class="row g-3" id="device-grid">
    @forelse($devices as $device)
        @php
            $statusColor = $device->status_color;
            $statusLabel = $device->isOnline() ? 'online' : ($device->last_seen_at && $device->last_seen_at->diffInMinutes(now()) < 30 ? 'warning' : 'offline');
        @endphp
        <div class="col-sm-6 col-lg-4 col-xl-3">
            <a href="{{ route('epayplus.fleet.device', $device) }}" class="text-decoration-none">
                <div class="card device-card shadow-sm h-100 status-{{ $statusLabel }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center">
                                <span class="pulse-dot {{ $statusLabel }} me-2"></span>
                                <h6 class="mb-0 text-dark fw-semibold">{{ Str::limit($device->name, 20) }}</h6>
                            </div>
                            @if($device->is_locked)
                                <span class="badge bg-dark"><i class="bi bi-lock-fill"></i></span>
                            @endif
                        </div>

                        <div class="small text-muted mb-2">
                            <i class="bi bi-phone me-1"></i>{{ $device->model ?? 'Unknown' }}
                            @if($device->group)
                                <span class="ms-2 badge" style="background: {{ $device->group->color }}">{{ $device->group->name }}</span>
                            @endif
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">
                                <i class="bi bi-clock me-1"></i>
                                {{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Never' }}
                            </small>
                            <small class="text-muted">
                                v{{ $device->app_version ?? '?' }}
                            </small>
                        </div>

                        @if($device->battery_level !== null)
                        <div class="d-flex align-items-center gap-2">
                            <div class="battery-bar flex-grow-1">
                                <div class="battery-fill {{ $device->battery_level > 50 ? 'bg-success' : ($device->battery_level > 20 ? 'bg-warning' : 'bg-danger') }}"
                                     style="width: {{ $device->battery_level }}%"></div>
                            </div>
                            <small class="text-muted">{{ $device->battery_level }}%</small>
                            @if($device->signal_strength !== null)
                                <i class="bi {{ $device->signal_icon }} text-muted"></i>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
            </a>
        </div>
    @empty
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-phone display-3 text-muted"></i>
                    <h5 class="mt-3 text-muted">No devices found</h5>
                    <p class="text-muted">Devices will appear here once they connect and register.</p>
                </div>
            </div>
        </div>
    @endforelse
</div>

<div class="mt-4">
    {{ $devices->links() }}
</div>

{{-- Bulk Command Modal --}}
<div class="modal fade" id="bulkCommandModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('epayplus.fleet.bulk-command') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-terminal me-2"></i>Bulk Command</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Target Group</label>
                        <select name="group_id" class="form-select">
                            <option value="">Select a group...</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Command</label>
                        <select name="command" class="form-select" required>
                            <option value="reboot">Reboot</option>
                            <option value="lock">Lock Device</option>
                            <option value="unlock">Unlock Device</option>
                            <option value="update_config">Refresh Config</option>
                            <option value="clear_cache">Clear Cache</option>
                            <option value="force_sync">Force Sync</option>
                            <option value="screenshot">Capture Screenshot</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-send me-1"></i> Send to Group
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function refreshStatus() {
    fetch('{{ route("epayplus.fleet.live-status") }}')
        .then(r => r.json())
        .then(data => {
            document.getElementById('online-count').textContent = data.stats.online;
            document.getElementById('offline-count').textContent = data.stats.offline;
        })
        .catch(() => {});
}
setInterval(refreshStatus, 30000);
</script>
@endpush
