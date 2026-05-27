@extends('layouts.epayplus')
@section('title', 'OTA Update Manager')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('epayplus.fleet.dashboard') }}">Fleet Dashboard</a></li>
        <li class="breadcrumb-item active">OTA Updates</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><i class="bi bi-cloud-arrow-up text-primary me-2"></i>OTA Update Manager</h4>
        <p class="text-muted mb-0">Manage and deploy app updates to your device fleet</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
        <i class="bi bi-upload me-1"></i> Upload New Version
    </button>
</div>

{{-- Summary --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-primary">{{ $updates->total() }}</div>
                <small class="text-muted">Total Versions</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-success">{{ $updates->where('status', 'active')->count() }}</div>
                <small class="text-muted">Active Rollouts</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-info">{{ $totalDevices }}</div>
                <small class="text-muted">Total Devices</small>
            </div>
        </div>
    </div>
</div>

{{-- Updates List --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-list-ul me-2"></i>Update History
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Version</th>
                    <th>Status</th>
                    <th>Rollout</th>
                    <th>Progress</th>
                    <th>Released</th>
                    <th>Size</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($updates as $update)
                <tr>
                    <td>
                        <strong>v{{ $update->version }}</strong>
                        <br><small class="text-muted">{{ $update->filename }}</small>
                    </td>
                    <td>
                        @php
                            $badgeMap = ['draft' => 'secondary', 'active' => 'success', 'paused' => 'warning', 'completed' => 'info', 'rolled_back' => 'danger'];
                        @endphp
                        <span class="badge bg-{{ $badgeMap[$update->status] ?? 'secondary' }}">{{ ucfirst($update->status) }}</span>
                    </td>
                    <td>
                        <span class="text-capitalize">{{ $update->rollout_type }}</span>
                        @if($update->rollout_type === 'staged')
                            <small class="text-muted">({{ $update->rollout_percentage }}%)</small>
                        @endif
                        @if($update->targetGroup)
                            <br><small class="text-muted">{{ $update->targetGroup->name }}</small>
                        @endif
                    </td>
                    <td>
                        @php
                            $total = $update->success_count + $update->failure_count + $update->pending_count;
                            $pct = $total > 0 ? round(($update->success_count / $total) * 100) : 0;
                        @endphp
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height: 6px; min-width: 80px;">
                                <div class="progress-bar bg-success" style="width: {{ $pct }}%"></div>
                                @if($update->failure_count > 0)
                                    <div class="progress-bar bg-danger" style="width: {{ $total > 0 ? round(($update->failure_count / $total) * 100) : 0 }}%"></div>
                                @endif
                            </div>
                            <small>{{ $update->success_count }}/{{ $total }}</small>
                        </div>
                    </td>
                    <td><small>{{ $update->released_at?->format('M d, Y H:i') ?? '—' }}</small></td>
                    <td><small>{{ number_format($update->file_size / 1048576, 1) }} MB</small></td>
                    <td class="text-end">
                        @if($update->status === 'draft')
                            <form method="POST" action="{{ route('epayplus.fleet.updates.release', $update) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-success" onclick="return confirm('Release this update?')">
                                    <i class="bi bi-rocket"></i> Release
                                </button>
                            </form>
                        @elseif($update->status === 'active')
                            <form method="POST" action="{{ route('epayplus.fleet.updates.pause', $update) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-warning"><i class="bi bi-pause"></i> Pause</button>
                            </form>
                            <form method="POST" action="{{ route('epayplus.fleet.updates.rollback', $update) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-danger" onclick="return confirm('Rollback this update? Pending devices will be skipped.')">
                                    <i class="bi bi-arrow-counterclockwise"></i> Rollback
                                </button>
                            </form>
                        @elseif($update->status === 'paused')
                            <form method="POST" action="{{ route('epayplus.fleet.updates.release', $update) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-success"><i class="bi bi-play"></i> Resume</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        <i class="bi bi-cloud-arrow-up d-block fs-3 mb-2"></i>
                        No OTA updates uploaded yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($updates->hasPages())
    <div class="card-footer">{{ $updates->links() }}</div>
    @endif
</div>

{{-- Upload Modal --}}
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('epayplus.fleet.updates.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-upload me-2"></i>Upload New OTA Version</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Version Number</label>
                            <input type="text" name="version" class="form-control" placeholder="3.1.0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">APK File</label>
                            <input type="file" name="apk_file" class="form-control" accept=".apk" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Release Notes</label>
                            <textarea name="release_notes" class="form-control" rows="3" placeholder="What's new in this version..."></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Rollout Type</label>
                            <select name="rollout_type" class="form-select" id="rolloutType">
                                <option value="all">All Devices</option>
                                <option value="staged">Staged (%)</option>
                                <option value="group">Specific Group</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="percentageField" style="display:none;">
                            <label class="form-label">Rollout Percentage</label>
                            <input type="number" name="rollout_percentage" class="form-control" min="1" max="100" value="25">
                        </div>
                        <div class="col-md-4" id="groupField" style="display:none;">
                            <label class="form-label">Target Group</label>
                            <select name="target_group_id" class="form-select">
                                <option value="">Select group...</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i> Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('rolloutType').addEventListener('change', function() {
    document.getElementById('percentageField').style.display = this.value === 'staged' ? '' : 'none';
    document.getElementById('groupField').style.display = this.value === 'group' ? '' : 'none';
});
</script>
@endpush
