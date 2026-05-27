@extends('layouts.epayplus')
@section('title', 'Device Groups')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('epayplus.fleet.dashboard') }}">Fleet Dashboard</a></li>
        <li class="breadcrumb-item active">Groups</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><i class="bi bi-collection text-secondary me-2"></i>Device Groups</h4>
        <p class="text-muted mb-0">Organize devices into groups for easier management</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGroupModal">
        <i class="bi bi-plus-lg me-1"></i> Create Group
    </button>
</div>

<div class="row g-4">
    @forelse($groups as $group)
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100" style="border-top: 4px solid {{ $group->color }} !important;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="mb-0 fw-semibold">{{ $group->name }}</h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="{{ route('epayplus.fleet.dashboard', ['group_id' => $group->id]) }}">
                                    <i class="bi bi-eye me-2"></i>View Devices
                                </a>
                            </li>
                            <li>
                                <form method="POST" action="{{ route('epayplus.fleet.groups.delete', $group) }}"
                                      onsubmit="return confirm('Delete this group? Devices will be unassigned.')">
                                    @csrf @method('DELETE')
                                    <button class="dropdown-item text-danger">
                                        <i class="bi bi-trash me-2"></i>Delete
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
                @if($group->description)
                    <p class="text-muted small mb-2">{{ $group->description }}</p>
                @endif
                @if($group->location)
                    <p class="text-muted small mb-2"><i class="bi bi-geo-alt me-1"></i>{{ $group->location }}</p>
                @endif
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <span class="badge bg-light text-dark fs-6">
                        <i class="bi bi-phone me-1"></i>{{ $group->devices_count }} devices
                    </span>
                    <span class="badge bg-{{ $group->is_active ? 'success' : 'secondary' }}">
                        {{ $group->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-collection display-3 text-muted"></i>
                <h5 class="mt-3 text-muted">No groups created yet</h5>
                <p class="text-muted">Create groups to organize your devices by location or function.</p>
            </div>
        </div>
    </div>
    @endforelse
</div>

{{-- Add Group Modal --}}
<div class="modal fade" id="addGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('epayplus.fleet.groups.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Create Device Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Group Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g., Manila Branch" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control" placeholder="Optional description">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control" placeholder="e.g., Makati City">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color Tag</label>
                        <input type="color" name="color" class="form-control form-control-color" value="#0d6efd">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
