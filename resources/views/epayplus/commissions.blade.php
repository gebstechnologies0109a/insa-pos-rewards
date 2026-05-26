@extends('layouts.epayplus')
@section('title', 'Commission Management')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Commission Management</h4>
        <p class="text-muted mb-0">Configure commission rates and rules</p>
    </div>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCommissionModal">
        <i class="bi bi-plus-lg me-1"></i> Add Rule
    </button>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-primary">{{ $stats['total_rules'] }}</div>
                <small class="text-muted">Total Rules</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-success">{{ $stats['active_rules'] }}</div>
                <small class="text-muted">Active Rules</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-info">{{ $stats['providers_covered'] }}</div>
                <small class="text-muted">Providers</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-warning">{{ $stats['retailer_overrides'] }}</div>
                <small class="text-muted">Retailer Overrides</small>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <form method="GET" class="row g-2">
            <div class="col-md-3">
                <input type="text" name="provider_code" class="form-control form-control-sm" placeholder="Provider Code" value="{{ request('provider_code') }}">
            </div>
            <div class="col-md-3">
                <select name="tier" class="form-select form-select-sm">
                    <option value="">All Tiers</option>
                    @foreach(['default','silver','gold','platinum'] as $tier)
                    <option value="{{ $tier }}" {{ request('tier') == $tier ? 'selected' : '' }}>{{ ucfirst($tier) }}</option>
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
                        <th>Provider</th>
                        <th>Product</th>
                        <th>Retailer</th>
                        <th>Rate</th>
                        <th>Type</th>
                        <th>Tier</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($commissions as $comm)
                    <tr>
                        <td><code>{{ $comm->provider_code ?? 'ALL' }}</code></td>
                        <td><code>{{ $comm->product_code ?? 'ALL' }}</code></td>
                        <td>{{ $comm->retailer_id ?? 'All Retailers' }}</td>
                        <td class="fw-medium">
                            {{ $comm->type === 'percentage' ? $comm->rate . '%' : '₱' . number_format($comm->rate, 2) }}
                        </td>
                        <td><span class="badge bg-light text-dark">{{ ucfirst($comm->type) }}</span></td>
                        <td>
                            @php $tc = ['default'=>'secondary','silver'=>'info','gold'=>'warning','platinum'=>'primary']; @endphp
                            <span class="badge bg-{{ $tc[$comm->tier] ?? 'secondary' }}">{{ ucfirst($comm->tier) }}</span>
                        </td>
                        <td>
                            <span class="badge bg-{{ $comm->is_active ? 'success' : 'danger' }}">
                                {{ $comm->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('epayplus.commissions.toggle', $comm) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-{{ $comm->is_active ? 'warning' : 'success' }}" title="Toggle">
                                    <i class="bi bi-toggle-{{ $comm->is_active ? 'on' : 'off' }}"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('epayplus.commissions.destroy', $comm) }}" class="d-inline" onsubmit="return confirm('Delete this rule?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No commission rules configured.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($commissions->hasPages())
    <div class="card-footer bg-white">{{ $commissions->links() }}</div>
    @endif
</div>

{{-- Add Commission Modal --}}
<div class="modal fade" id="addCommissionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('epayplus.commissions.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Commission Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Provider Code</label>
                        <input type="text" name="provider_code" class="form-control" placeholder="Leave blank for all providers">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Product Code</label>
                        <input type="text" name="product_code" class="form-control" placeholder="Leave blank for all products">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Retailer ID</label>
                        <input type="number" name="retailer_id" class="form-control" placeholder="Leave blank for all retailers">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label class="form-label">Rate</label>
                            <input type="number" step="0.01" name="rate" class="form-control" required>
                        </div>
                        <div class="col">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-select" required>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed (₱)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tier</label>
                        <select name="tier" class="form-select" required>
                            <option value="default">Default</option>
                            <option value="silver">Silver</option>
                            <option value="gold">Gold</option>
                            <option value="platinum">Platinum</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
