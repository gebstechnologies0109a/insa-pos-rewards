@extends('layouts.epayplus')

@section('title', 'Shop Inventory')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Shop Inventory</h4>
        <small class="text-muted">Retail products for POS Mode (per retailer)</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('epayplus.pos', ['retailer_id' => $retailerId]) }}" class="btn btn-success">
            <i class="bi bi-cart-check"></i> Open POS Mode
        </a>
        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addProductModal">
            <i class="bi bi-plus-lg"></i> Add Item
        </button>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form method="get" class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small">Retailer</label>
            <select name="retailer_id" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach($retailers as $r)
                    <option value="{{ $r->id }}" @selected($retailerId == $r->id)>{{ $r->business_name }} ({{ $r->account_id }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Name or SKU">
        </div>
        <div class="col-md-2">
            <button class="btn btn-sm btn-secondary w-100">Filter</button>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th class="text-end">Price</th>
                    <th class="text-center">Stock</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $p)
                <tr>
                    <td>
                        <strong>{{ $p->name }}</strong>
                        @if($p->description)<br><small class="text-muted">{{ Str::limit($p->description, 60) }}</small>@endif
                    </td>
                    <td>{{ $p->sku ?: '—' }}</td>
                    <td>{{ $p->category ?: 'General' }}</td>
                    <td class="text-end">₱{{ number_format($p->price, 2) }}</td>
                    <td class="text-center">{{ $p->stock }}</td>
                    <td>
                        @if($p->is_active)<span class="badge bg-success">Active</span>
                        @else<span class="badge bg-secondary">Inactive</span>@endif
                    </td>
                    <td class="text-end text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal{{ $p->id }}">Edit</button>
                        <form action="{{ route('epayplus.retail-products.destroy', $p) }}" method="post" class="d-inline" onsubmit="return confirm('Delete this item?')">
                            @csrf @method('DELETE')
                            <input type="hidden" name="retailer_id" value="{{ $retailerId }}">
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                @include('epayplus.retail-products._edit-modal', ['product' => $p, 'retailerId' => $retailerId])
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No shop items yet. Add your first retail product.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($products->hasPages())
        <div class="card-footer">{{ $products->links() }}</div>
    @endif
</div>

@include('epayplus.retail-products._add-modal', ['retailerId' => $retailerId])
@endsection
