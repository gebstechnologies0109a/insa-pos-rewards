@extends('layouts.epayplus')
@section('title', 'Product Pricing')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Pricing & Discounts</h4>
        <small class="text-muted">Retailer-specific product pricing and commission overrides</small>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold"><i class="bi bi-tag me-2"></i>New Pricing Rule</div>
            <div class="card-body">
                <form method="POST" action="{{ route('epayplus.pricing.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <select name="product_id" class="form-select">
                            <option value="">— Select —</option>
                            @foreach($products as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} (₱{{ number_format($p->amount, 2) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Retailer (blank = global)</label>
                        <select name="retailer_id" class="form-select">
                            <option value="">All Retailers</option>
                            @foreach($retailers as $r)
                            <option value="{{ $r->id }}">{{ $r->business_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Discount Type</label>
                        <select name="discount_type" class="form-select">
                            <option value="percentage">Percentage Off</option>
                            <option value="fixed">Fixed Amount Off</option>
                            <option value="override">Override Price</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Discount Value</label>
                        <input type="number" step="0.01" name="discount_value" class="form-control" value="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Custom Price (override only)</label>
                        <input type="number" step="0.01" name="custom_price" class="form-control">
                    </div>
                    <button class="btn btn-success w-100">Save Rule</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Product</th><th>Retailer</th><th>Type</th><th>Value</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($pricing as $rule)
                        <tr>
                            <td>{{ $rule->product?->name ?? $rule->product_code ?? '—' }}</td>
                            <td><small>{{ $rule->retailer?->business_name ?? 'Global' }}</small></td>
                            <td>{{ $rule->discount_type }}</td>
                            <td>
                                @if($rule->discount_type === 'override')
                                    ₱{{ number_format($rule->custom_price ?? 0, 2) }}
                                @elseif($rule->discount_type === 'percentage')
                                    {{ $rule->discount_value }}%
                                @else
                                    ₱{{ number_format($rule->discount_value, 2) }} off
                                @endif
                            </td>
                            <td><span class="badge bg-{{ $rule->is_active ? 'success' : 'secondary' }}">{{ $rule->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                <form method="POST" action="{{ route('epayplus.pricing.destroy', $rule) }}" class="d-inline" onsubmit="return confirm('Delete?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No pricing rules configured.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($pricing->hasPages())
            <div class="card-footer bg-transparent">{{ $pricing->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
