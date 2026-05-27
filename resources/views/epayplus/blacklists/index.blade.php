@extends('layouts.epayplus')
@section('title', 'Blacklist')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Blacklist</h4>
        <small class="text-muted">Block phones, accounts, devices, and machines from transacting</small>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold"><i class="bi bi-shield-x me-2"></i>Add Entry</div>
            <div class="card-body">
                <form method="POST" action="{{ route('epayplus.blacklists.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select" required>
                            <option value="phone">Phone Number</option>
                            <option value="account">Account ID</option>
                            <option value="device">Device ID</option>
                            <option value="machine">Machine UID (09NET* / EPAY*)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Value</label>
                        <input type="text" name="value" class="form-control" required placeholder="e.g. 09171234567 or 09NET256071439">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea name="reason" class="form-control" rows="2"></textarea>
                    </div>
                    <button class="btn btn-danger w-100"><i class="bi bi-ban me-1"></i> Block</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between">
                <span class="fw-semibold">Blocked Entries</span>
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="{{ request('search') }}">
                    <button class="btn btn-sm btn-outline-secondary">Filter</button>
                </form>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Type</th><th>Value</th><th>Reason</th><th>Status</th><th>Blocked</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $entry)
                        <tr>
                            <td><span class="badge bg-dark">{{ $entry->type }}</span></td>
                            <td><code>{{ $entry->value }}</code></td>
                            <td><small>{{ $entry->reason ?? '—' }}</small></td>
                            <td>
                                <span class="badge bg-{{ $entry->is_active ? 'danger' : 'secondary' }}">
                                    {{ $entry->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td><small>{{ $entry->blocked_at?->format('M d, Y') ?? $entry->created_at->format('M d, Y') }}</small></td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('epayplus.blacklists.toggle', $entry) }}" class="d-inline">@csrf
                                    <button class="btn btn-sm btn-outline-warning">{{ $entry->is_active ? 'Disable' : 'Enable' }}</button>
                                </form>
                                <form method="POST" action="{{ route('epayplus.blacklists.destroy', $entry) }}" class="d-inline" onsubmit="return confirm('Remove this entry?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No blacklist entries.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($entries->hasPages())
            <div class="card-footer bg-transparent">{{ $entries->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
