@extends('layouts.epayplus')
@section('title', 'Device: ' . ($device->name ?? $device->device_id))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('epayplus.devices') }}">Devices</a></li>
                <li class="breadcrumb-item active">{{ $device->name ?? $device->device_id }}</li>
            </ol>
        </nav>
        <h4 class="mb-0">{{ $device->name ?? 'Device Details' }}</h4>
    </div>
    <div>
        <span class="badge bg-{{ $device->status === 'online' ? 'success' : 'danger' }} fs-6">
            <i class="bi bi-circle-fill me-1" style="font-size:0.6rem"></i> {{ ucfirst($device->status) }}
        </span>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-medium">Device Info</div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted">Device ID</td><td class="fw-medium">{{ $device->device_id }}</td></tr>
                    <tr><td class="text-muted">Type</td><td><span class="badge bg-info">{{ ucfirst($device->type) }}</span></td></tr>
                    <tr><td class="text-muted">Model</td><td>{{ $device->model ?? '—' }}</td></tr>
                    <tr><td class="text-muted">OS Version</td><td>{{ $device->os_version ?? '—' }}</td></tr>
                    <tr><td class="text-muted">App Version</td><td>{{ $device->app_version ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Location</td><td>{{ $device->location ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Group</td><td>{{ $device->group_zone ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Hours</td><td>{{ $device->operating_hours ?? '24/7' }}</td></tr>
                    <tr><td class="text-muted">Registered</td><td>{{ $device->registered_at?->format('M d, Y') ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Last Seen</td><td>{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</td></tr>
                </table>
            </div>
        </div>

        @if($device->config && isset($device->config['last_heartbeat']))
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white fw-medium">Health Metrics</div>
            <div class="card-body">
                @php $hb = $device->config['last_heartbeat']; @endphp
                <div class="mb-2">
                    <small class="text-muted">Battery</small>
                    <div class="progress" style="height:6px">
                        <div class="progress-bar bg-{{ ($hb['battery_level'] ?? 0) > 20 ? 'success' : 'danger' }}" style="width:{{ $hb['battery_level'] ?? 0 }}%"></div>
                    </div>
                    <small>{{ $hb['battery_level'] ?? '—' }}%</small>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Storage Free</small>
                    <div>{{ $hb['free_storage_mb'] ?? '—' }} MB</div>
                </div>
                <div>
                    <small class="text-muted">Active Transactions</small>
                    <div>{{ $hb['active_transactions'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        @endif
    </div>

    <div class="col-lg-8">
        {{-- Commands --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-medium">Recent Commands</span>
                <form method="POST" action="{{ route('epayplus.devices.command', $device) }}" class="d-flex gap-2">
                    @csrf
                    <select name="command" class="form-select form-select-sm" style="width:180px" required>
                        <option value="restart_app">Restart App</option>
                        <option value="enable_kiosk">Enable Kiosk</option>
                        <option value="disable_kiosk">Disable Kiosk</option>
                        <option value="sync_products">Sync Products</option>
                        <option value="clear_cache">Clear Cache</option>
                        <option value="lock_device">Lock Device</option>
                    </select>
                    <button class="btn btn-sm btn-warning">Send</button>
                </form>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Command</th><th>Status</th><th>Sent</th><th>Executed</th></tr>
                    </thead>
                    <tbody>
                        @forelse($device->commands as $cmd)
                        <tr>
                            <td><code>{{ $cmd->command }}</code></td>
                            <td>
                                @php $sc = ['pending'=>'warning','sent'=>'info','acknowledged'=>'success','failed'=>'danger','expired'=>'secondary']; @endphp
                                <span class="badge bg-{{ $sc[$cmd->status] ?? 'secondary' }}">{{ $cmd->status }}</span>
                            </td>
                            <td><small>{{ $cmd->sent_at?->format('M d H:i') ?? '—' }}</small></td>
                            <td><small>{{ $cmd->executed_at?->format('M d H:i') ?? '—' }}</small></td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No commands sent.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Logs --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-medium">Device Logs</span>
                <a href="{{ route('epayplus.devices.logs', $device) }}" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Level</th><th>Tag</th><th>Message</th><th>Time</th></tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr>
                            <td>
                                @php $lc = ['error'=>'danger','critical'=>'danger','warning'=>'warning','info'=>'info','debug'=>'secondary']; @endphp
                                <span class="badge bg-{{ $lc[$log->level] ?? 'secondary' }}">{{ $log->level }}</span>
                            </td>
                            <td><small>{{ $log->tag ?? '—' }}</small></td>
                            <td><small class="text-truncate d-inline-block" style="max-width:300px">{{ $log->message }}</small></td>
                            <td><small>{{ $log->created_at?->format('H:i:s') }}</small></td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No logs yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
