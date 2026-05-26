@extends('layouts.epayplus')
@section('title', 'Device Management')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Device Management</h4>
        <p class="text-muted mb-0">Monitor and manage all connected devices</p>
    </div>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
        <i class="bi bi-plus-lg me-1"></i> Register Device
    </button>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-primary">{{ $stats['total'] }}</div>
                <small class="text-muted">Total Devices</small>
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
                <div class="fs-3 fw-bold text-danger">{{ $stats['offline'] }}</div>
                <small class="text-muted">Offline</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-info">{{ $stats['kiosks'] }}</div>
                <small class="text-muted">Kiosks</small>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search devices..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="retailer" {{ request('type') == 'retailer' ? 'selected' : '' }}>Retailer</option>
                    <option value="kiosk" {{ request('type') == 'kiosk' ? 'selected' : '' }}>Kiosk</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="online" {{ request('status') == 'online' ? 'selected' : '' }}>Online</option>
                    <option value="offline" {{ request('status') == 'offline' ? 'selected' : '' }}>Offline</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="group" class="form-select form-select-sm">
                    <option value="">All Groups</option>
                    @foreach($groups as $group)
                    <option value="{{ $group }}" {{ request('group') == $group ? 'selected' : '' }}>{{ $group }}</option>
                    @endforeach
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
                        <th>Device</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Location</th>
                        <th>Version</th>
                        <th>Last Seen</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($devices as $device)
                    <tr>
                        <td>
                            <div class="fw-medium">{{ $device->name ?? 'Unnamed' }}</div>
                            <small class="text-muted">{{ $device->device_id }}</small>
                        </td>
                        <td>
                            <span class="badge bg-{{ $device->type === 'kiosk' ? 'info' : 'secondary' }}">
                                {{ ucfirst($device->type) }}
                            </span>
                        </td>
                        <td>
                            @php
                                $statusColors = ['online' => 'success', 'offline' => 'danger', 'inactive' => 'warning'];
                            @endphp
                            <span class="badge bg-{{ $statusColors[$device->status] ?? 'secondary' }}">
                                <i class="bi bi-circle-fill me-1" style="font-size:0.5rem"></i>
                                {{ ucfirst($device->status) }}
                            </span>
                        </td>
                        <td>{{ $device->location ?? '—' }}</td>
                        <td><small>{{ $device->app_version ?? '—' }}</small></td>
                        <td>
                            <small>{{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Never' }}</small>
                        </td>
                        <td>
                            <a href="{{ route('epayplus.devices.show', $device) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#commandModal{{ $device->id }}">
                                <i class="bi bi-terminal"></i>
                            </button>
                        </td>
                    </tr>

                    {{-- Command Modal --}}
                    <div class="modal fade" id="commandModal{{ $device->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('epayplus.devices.command', $device) }}">
                                    @csrf
                                    <div class="modal-header">
                                        <h5 class="modal-title">Send Command to {{ $device->name }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Command</label>
                                            <select name="command" class="form-select" required>
                                                <option value="restart_app">Restart App</option>
                                                <option value="enable_kiosk">Enable Kiosk Mode</option>
                                                <option value="disable_kiosk">Disable Kiosk Mode</option>
                                                <option value="update_config">Update Configuration</option>
                                                <option value="clear_cache">Clear Cache</option>
                                                <option value="sync_products">Sync Products</option>
                                                <option value="lock_device">Lock Device</option>
                                                <option value="unlock_device">Unlock Device</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-warning">Send Command</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No devices registered yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($devices->hasPages())
    <div class="card-footer bg-white">{{ $devices->links() }}</div>
    @endif
</div>

{{-- Add Device Modal --}}
<div class="modal fade" id="addDeviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('epayplus.devices.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Register New Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Device ID</label>
                        <input type="text" name="device_id" class="form-control" required placeholder="Unique device identifier">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Device Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="Friendly name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select" required>
                            <option value="retailer">Retailer Device</option>
                            <option value="kiosk">Kiosk</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control" placeholder="Physical location">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Group / Zone</label>
                        <input type="text" name="group_zone" class="form-control" placeholder="e.g. Manila North">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Register</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
