@extends('layouts.epayplus')
@section('title', $device->name . ' — Device Detail')

@push('styles')
<style>
    .info-label { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; margin-bottom: .25rem; }
    .info-value { font-weight: 500; }
    .command-badge { font-size: .7rem; }
    .log-entry { font-family: 'Courier New', monospace; font-size: .8rem; padding: .4rem .6rem; border-bottom: 1px solid #f0f0f0; }
    .log-entry:last-child { border-bottom: none; }
    .log-entry.level-error, .log-entry.level-critical { background: #fff5f5; }
    .log-entry.level-warning { background: #fffbeb; }
    .action-btn { min-width: 120px; }
    .timeline-item { position: relative; padding-left: 1.5rem; padding-bottom: 1rem; border-left: 2px solid #e9ecef; }
    .timeline-item:last-child { border-left-color: transparent; padding-bottom: 0; }
    .timeline-dot { position: absolute; left: -6px; top: 4px; width: 10px; height: 10px; border-radius: 50%; background: #6c757d; }
    .timeline-dot.pending { background: #ffc107; }
    .timeline-dot.sent { background: #0dcaf0; }
    .timeline-dot.acknowledged { background: #0d6efd; }
    .timeline-dot.executed { background: #198754; }
    .timeline-dot.failed { background: #dc3545; }
</style>
@endpush

@section('content')
{{-- Breadcrumb --}}
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('epayplus.fleet.dashboard') }}">Fleet Dashboard</a></li>
        <li class="breadcrumb-item active">{{ $device->name }}</li>
    </ol>
</nav>

{{-- Header --}}
<div class="d-flex justify-content-between align-items-start mb-4">
    <div class="d-flex align-items-center">
        <div class="me-3">
            @php $statusColor = $device->status_color; @endphp
            <div class="rounded-circle bg-{{ $statusColor }} bg-opacity-10 d-flex align-items-center justify-content-center"
                 style="width: 56px; height: 56px;">
                <i class="bi bi-phone fs-4 text-{{ $statusColor }}"></i>
            </div>
        </div>
        <div>
            <h4 class="mb-0 fw-bold">{{ $device->name }}</h4>
            <div class="text-muted">
                <span class="badge bg-{{ $statusColor }} me-1">{{ $device->isOnline() ? 'Online' : 'Offline' }}</span>
                <span class="me-2">{{ $device->device_id }}</span>
                @if($device->group)
                    <span class="badge" style="background: {{ $device->group->color }}">{{ $device->group->name }}</span>
                @endif
                @if($device->is_locked)
                    <span class="badge bg-dark ms-1"><i class="bi bi-lock-fill me-1"></i>Locked</span>
                @endif
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editDeviceModal">
            <i class="bi bi-pencil me-1"></i> Edit
        </button>
        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#commandModal">
            <i class="bi bi-terminal me-1"></i> Send Command
        </button>
    </div>
</div>

<div class="row g-4">
    {{-- Device Info --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-info-circle me-2"></i>Device Information
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="info-label">Model</div>
                        <div class="info-value">{{ $device->model ?? '—' }}</div>
                    </div>
                    <div class="col-6">
                        <div class="info-label">Serial</div>
                        <div class="info-value">{{ $device->serial_number ?? '—' }}</div>
                    </div>
                    <div class="col-6">
                        <div class="info-label">OS Version</div>
                        <div class="info-value">{{ $device->os_version ?? '—' }}</div>
                    </div>
                    <div class="col-6">
                        <div class="info-label">App Version</div>
                        <div class="info-value">{{ $device->app_version ?? '—' }}</div>
                    </div>
                    <div class="col-6">
                        <div class="info-label">Type</div>
                        <div class="info-value text-capitalize">{{ $device->type }}</div>
                    </div>
                    <div class="col-6">
                        <div class="info-label">Registered</div>
                        <div class="info-value">{{ $device->registered_at?->format('M d, Y') ?? '—' }}</div>
                    </div>
                    <div class="col-6">
                        <div class="info-label">Location</div>
                        <div class="info-value">{{ $device->location ?? '—' }}</div>
                    </div>
                    <div class="col-6">
                        <div class="info-label">IP Address</div>
                        <div class="info-value">{{ $device->ip_address ?? '—' }}</div>
                    </div>
                    @if($device->retailer)
                    <div class="col-12">
                        <div class="info-label">Assigned Retailer</div>
                        <div class="info-value">{{ $device->retailer->business_name ?? $device->retailer->owner_name }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Telemetry --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-activity me-2"></i>Live Telemetry
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted">Battery</small>
                        <small class="fw-medium">{{ $device->battery_level ?? '?' }}%</small>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar {{ ($device->battery_level ?? 0) > 50 ? 'bg-success' : (($device->battery_level ?? 0) > 20 ? 'bg-warning' : 'bg-danger') }}"
                             style="width: {{ $device->battery_level ?? 0 }}%"></div>
                    </div>
                </div>
                <div class="row g-2 text-center">
                    <div class="col-4">
                        <div class="border rounded p-2">
                            <i class="bi {{ $device->signal_icon }} d-block mb-1"></i>
                            <small class="text-muted d-block">Signal</small>
                            <small class="fw-medium">{{ $device->signal_strength ?? '?' }} dBm</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-2">
                            <i class="bi bi-hdd d-block mb-1"></i>
                            <small class="text-muted d-block">Storage</small>
                            <small class="fw-medium">{{ $device->free_storage_mb ? $device->free_storage_mb . ' MB' : '?' }}</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-2">
                            <i class="bi bi-broadcast d-block mb-1"></i>
                            <small class="text-muted d-block">Network</small>
                            <small class="fw-medium">{{ $device->network_type ?? '?' }}</small>
                        </div>
                    </div>
                </div>
                <div class="mt-3 text-center">
                    <small class="text-muted">
                        <i class="bi bi-clock me-1"></i>Last seen: {{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Never' }}
                    </small>
                </div>
            </div>
        </div>

        {{-- Active Alerts --}}
        @if($activeAlerts->isNotEmpty())
        <div class="card border-0 shadow-sm border-start border-warning border-3 mb-4">
            <div class="card-header bg-transparent fw-semibold text-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>Active Alerts ({{ $activeAlerts->count() }})
            </div>
            <div class="card-body p-0">
                @foreach($activeAlerts as $alert)
                <div class="d-flex align-items-start p-3 border-bottom">
                    <span class="badge bg-{{ $alert->severity === 'critical' ? 'danger' : ($alert->severity === 'warning' ? 'warning' : 'info') }} me-2">
                        {{ strtoupper($alert->severity) }}
                    </span>
                    <div class="flex-grow-1">
                        <div class="fw-medium small">{{ $alert->title }}</div>
                        <small class="text-muted">{{ $alert->created_at->diffForHumans() }}</small>
                    </div>
                    <form method="POST" action="{{ route('epayplus.fleet.alert.resolve', $alert) }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-success" title="Resolve">
                            <i class="bi bi-check-lg"></i>
                        </button>
                    </form>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- Commands & Logs --}}
    <div class="col-lg-8">
        {{-- Quick Actions --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-lightning me-2"></i>Quick Actions
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    @foreach(['reboot' => ['Reboot', 'arrow-clockwise', 'warning'], 'lock' => ['Lock', 'lock', 'dark'], 'unlock' => ['Unlock', 'unlock', 'success'], 'screenshot' => ['Screenshot', 'camera', 'info'], 'force_sync' => ['Force Sync', 'arrow-repeat', 'primary'], 'clear_cache' => ['Clear Cache', 'trash', 'secondary'], 'update_config' => ['Refresh Config', 'gear', 'primary'], 'wipe' => ['Factory Reset', 'exclamation-octagon', 'danger']] as $cmd => [$label, $icon, $color])
                    <form method="POST" action="{{ route('epayplus.fleet.device.command', $device) }}" class="d-inline"
                          @if(in_array($cmd, ['wipe', 'lock'])) onsubmit="return confirm('Are you sure you want to {{ strtolower($label) }} this device?')" @endif>
                        @csrf
                        <input type="hidden" name="command" value="{{ $cmd }}">
                        <button class="btn btn-sm btn-outline-{{ $color }} action-btn">
                            <i class="bi bi-{{ $icon }} me-1"></i> {{ $label }}
                        </button>
                    </form>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Command History --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-terminal me-2"></i>Command History</span>
                <span class="badge bg-secondary">{{ $recentCommands->count() }}</span>
            </div>
            <div class="card-body p-0" style="max-height: 350px; overflow-y: auto;">
                @forelse($recentCommands as $cmd)
                <div class="timeline-item px-3 pt-3">
                    <div class="timeline-dot {{ $cmd->status }}"></div>
                    <div class="d-flex justify-content-between">
                        <div>
                            <code class="fw-bold">{{ $cmd->command }}</code>
                            <span class="badge command-badge bg-{{ $cmd->status === 'executed' ? 'success' : ($cmd->status === 'failed' ? 'danger' : ($cmd->status === 'pending' ? 'warning' : 'info')) }}">
                                {{ $cmd->status }}
                            </span>
                        </div>
                        <small class="text-muted">{{ $cmd->created_at->diffForHumans() }}</small>
                    </div>
                    @if($cmd->result)
                        <small class="text-muted d-block mt-1">{{ Str::limit($cmd->result, 100) }}</small>
                    @endif
                </div>
                @empty
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-terminal d-block fs-3 mb-2"></i>
                    No commands sent yet.
                </div>
                @endforelse
            </div>
        </div>

        {{-- Device Logs --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-journal-text me-2"></i>Recent Logs</span>
                <a href="{{ route('epayplus.devices.logs', $device) }}" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                @forelse($recentLogs as $log)
                <div class="log-entry level-{{ $log->level }}">
                    <span class="badge bg-{{ $log->level === 'error' || $log->level === 'critical' ? 'danger' : ($log->level === 'warning' ? 'warning' : ($log->level === 'info' ? 'info' : 'secondary')) }} me-2"
                          style="font-size: .65rem; min-width: 55px;">{{ strtoupper($log->level) }}</span>
                    <span>{{ Str::limit($log->message, 80) }}</span>
                    <small class="text-muted float-end">{{ $log->created_at->format('H:i:s') }}</small>
                </div>
                @empty
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-journal d-block fs-3 mb-2"></i>
                    No logs recorded.
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Send Command Modal --}}
<div class="modal fade" id="commandModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('epayplus.fleet.device.command', $device) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-terminal me-2"></i>Send Command</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
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
                            <option value="install_apk">Install APK</option>
                            <option value="push_notification">Push Notification</option>
                            <option value="set_volume">Set Volume</option>
                            <option value="wipe">Factory Reset (DANGER)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Parameters (JSON, optional)</label>
                        <textarea name="params_json" class="form-control font-monospace" rows="3"
                                  placeholder='{"key": "value"}'></textarea>
                        <small class="text-muted">Leave empty if no parameters needed.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-send me-1"></i> Send</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Device Modal --}}
<div class="modal fade" id="editDeviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('epayplus.fleet.device.update', $device) }}">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Device Name</label>
                        <input type="text" name="name" class="form-control" value="{{ $device->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Group</label>
                        <select name="group_id" class="form-select">
                            <option value="">No Group</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" {{ $device->group_id == $group->id ? 'selected' : '' }}>
                                    {{ $group->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Config Profile</label>
                        <select name="config_profile_id" class="form-select">
                            <option value="">Default</option>
                            @foreach($configs as $config)
                                <option value="{{ $config->id }}" {{ $device->config_profile_id == $config->id ? 'selected' : '' }}>
                                    {{ $config->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control" value="{{ $device->location }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
