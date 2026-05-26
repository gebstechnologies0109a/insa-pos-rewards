@extends('layouts.epayplus')

@section('title', 'Retailers')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Retailers</h4>
        <small class="text-muted">{{ $retailers->total() }} total retailers</small>
    </div>
    <a href="{{ route('epayplus.retailers.create') }}" class="btn btn-success">
        <i class="bi bi-plus-lg"></i> Add Retailer
    </a>
</div>

{{-- Search / Filter --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4 col-sm-6">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name, owner, mobile, account ID..." value="{{ request('search') }}">
            </div>
            <div class="col-auto">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="active" {{ request('status')=='active'?'selected':'' }}>Active</option>
                    <option value="inactive" {{ request('status')=='inactive'?'selected':'' }}>Inactive</option>
                </select>
            </div>
            <div class="col-auto">
                <select name="sort" class="form-select form-select-sm">
                    <option value="created_at" {{ request('sort','created_at')=='created_at'?'selected':'' }}>Newest</option>
                    <option value="business_name" {{ request('sort')=='business_name'?'selected':'' }}>Name</option>
                    <option value="balance" {{ request('sort')=='balance'?'selected':'' }}>Balance</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-success">Filter</button>
                <a href="{{ route('epayplus.retailers') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Account</th>
                        <th>Business Name</th>
                        <th>Owner</th>
                        <th>Mobile</th>
                        <th>Balance</th>
                        <th>Txns</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($retailers as $retailer)
                    <tr>
                        <td><code class="small">{{ $retailer->account_id }}</code></td>
                        <td class="fw-medium">{{ $retailer->business_name }}</td>
                        <td><small>{{ $retailer->owner_name }}</small></td>
                        <td><small>{{ $retailer->mobile_number }}</small></td>
                        <td class="fw-bold text-success">₱{{ number_format($retailer->balance, 2) }}</td>
                        <td><span class="badge bg-secondary">{{ $retailer->transactions_count }}</span></td>
                        <td>
                            <span class="badge {{ $retailer->is_active ? 'text-bg-success' : 'text-bg-danger' }}">
                                {{ $retailer->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td><small class="text-muted">{{ $retailer->created_at->format('M d, Y') }}</small></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('epayplus.retailers.show', $retailer) }}" class="btn btn-outline-success" title="View"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('epayplus.retailers.edit', $retailer) }}" class="btn btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No retailers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($retailers->hasPages())
    <div class="card-footer bg-transparent border-0">
        {{ $retailers->links() }}
    </div>
    @endif
</div>
@endsection
