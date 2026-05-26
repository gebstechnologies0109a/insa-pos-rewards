@extends('layouts.epayplus')

@section('title', 'Retailers')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Retailers</h2>
            <small class="text-muted">{{ $retailers->total() }} total retailers</small>
        </div>
        <a href="{{ route('epayplus.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Business Name</th>
                            <th>Owner</th>
                            <th>Mobile</th>
                            <th>Balance</th>
                            <th>Transactions</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($retailers as $retailer)
                        <tr>
                            <td class="text-muted">{{ $retailer->id }}</td>
                            <td class="fw-medium">{{ $retailer->business_name }}</td>
                            <td><small>{{ $retailer->owner_name }}</small></td>
                            <td><small>{{ $retailer->mobile_number }}</small></td>
                            <td class="fw-bold text-success">₱{{ number_format($retailer->balance, 2) }}</td>
                            <td><span class="badge bg-secondary">{{ $retailer->transactions_count }}</span></td>
                            <td>
                                @if($retailer->is_active)
                                    <span class="badge text-bg-success">Active</span>
                                @else
                                    <span class="badge text-bg-danger">Inactive</span>
                                @endif
                            </td>
                            <td><small class="text-muted">{{ $retailer->created_at->format('M d, Y') }}</small></td>
                            <td>
                                <a href="{{ route('epayplus.retailers.show', $retailer) }}" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No retailers found.</td>
                        </tr>
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
</div>
@endsection
