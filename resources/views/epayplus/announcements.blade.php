@extends('layouts.epayplus')

@section('title', 'Announcements')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Announcements</h4>
        <small class="text-muted">Manage retailer announcements</small>
    </div>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#annModal" onclick="resetAnnForm()">
        <i class="bi bi-plus-lg"></i> New Announcement
    </button>
</div>

<div class="row g-3">
    @forelse($announcements as $ann)
    <div class="col-md-6">
        <div class="card border-0 shadow-sm border-start border-3 border-{{ $ann->type }}">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="fw-bold mb-0">{{ $ann->title }}</h6>
                        <small class="text-muted">{{ $ann->created_at->format('M d, Y H:i') }}</small>
                    </div>
                    <span class="badge {{ $ann->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                        {{ $ann->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <p class="small mb-2">{{ Str::limit($ann->content, 200) }}</p>
                <div class="small text-muted mb-2">
                    <span class="badge bg-{{ $ann->type }}">{{ ucfirst($ann->type) }}</span>
                    @if($ann->starts_at) <span class="ms-1">From: {{ $ann->starts_at->format('M d') }}</span> @endif
                    @if($ann->expires_at) <span class="ms-1">Until: {{ $ann->expires_at->format('M d') }}</span> @endif
                </div>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-primary" onclick="editAnn({{ $ann->toJson() }})"><i class="bi bi-pencil"></i> Edit</button>
                    <form method="POST" action="{{ route('epayplus.announcements.toggle-status', $ann) }}" class="d-inline">
                        @csrf
                        <button class="btn btn-sm {{ $ann->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                            {{ $ann->is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('epayplus.announcements.destroy', $ann) }}" class="d-inline" onsubmit="return confirm('Delete this announcement?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12 text-center text-muted py-5">
        <i class="bi bi-megaphone fs-1"></i>
        <p class="mt-2">No announcements yet. Create one to notify all retailers.</p>
    </div>
    @endforelse
</div>

@if($announcements->hasPages())
<div class="mt-3">{{ $announcements->links() }}</div>
@endif

{{-- Announcement Modal --}}
<div class="modal fade" id="annModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="annForm">
                @csrf
                <div id="annMethod"></div>
                <div class="modal-header">
                    <h5 class="modal-title" id="annModalTitle">New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="aTitle" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content <span class="text-danger">*</span></label>
                        <textarea name="content" id="aContent" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Type</label>
                            <select name="type" id="aType" class="form-select">
                                <option value="info">Info</option>
                                <option value="success">Success</option>
                                <option value="warning">Warning</option>
                                <option value="danger">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Date</label>
                            <input type="datetime-local" name="starts_at" id="aStart" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expiry Date</label>
                            <input type="datetime-local" name="expires_at" id="aExpiry" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function resetAnnForm() {
    document.getElementById('annForm').action = '{{ route("epayplus.announcements.store") }}';
    document.getElementById('annMethod').innerHTML = '';
    document.getElementById('annModalTitle').textContent = 'New Announcement';
    ['aTitle','aContent','aStart','aExpiry'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('aType').value = 'info';
}

function editAnn(a) {
    document.getElementById('annForm').action = '/epayplus/announcements/' + a.id;
    document.getElementById('annMethod').innerHTML = '@method("PUT")';
    document.getElementById('annModalTitle').textContent = 'Edit Announcement';
    document.getElementById('aTitle').value = a.title;
    document.getElementById('aContent').value = a.content;
    document.getElementById('aType').value = a.type;
    document.getElementById('aStart').value = a.starts_at ? a.starts_at.slice(0,16) : '';
    document.getElementById('aExpiry').value = a.expires_at ? a.expires_at.slice(0,16) : '';
    new bootstrap.Modal(document.getElementById('annModal')).show();
}
</script>
@endpush
