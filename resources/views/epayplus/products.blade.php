@extends('layouts.epayplus')

@section('title', 'Products')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Products</h4>
        <small class="text-muted">{{ $products->total() }} products</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('epayplus.products.export') }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download"></i> Export CSV</a>
        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bi bi-upload"></i> Import</button>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#productModal" onclick="resetProductForm()"><i class="bi bi-plus-lg"></i> Add Product</button>
    </div>
</div>

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name or code..." value="{{ request('search') }}">
            </div>
            <div class="col-auto">
                <select name="provider_id" class="form-select form-select-sm">
                    <option value="">All Providers</option>
                    @foreach($providers as $p)
                    <option value="{{ $p->id }}" {{ request('provider_id')==$p->id?'selected':'' }}>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    @foreach(['ELOAD','BILLS','ECASH','WIFI','OTHER'] as $t)
                    <option value="{{ $t }}" {{ request('type')==$t?'selected':'' }}>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="active" {{ request('status')=='active'?'selected':'' }}>Active</option>
                    <option value="inactive" {{ request('status')=='inactive'?'selected':'' }}>Inactive</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-success">Filter</button>
                <a href="{{ route('epayplus.products') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
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
                        <th>Code</th>
                        <th>Name</th>
                        <th>Provider</th>
                        <th>Type</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Retailer Price</th>
                        <th class="text-end">Commission</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                    <tr>
                        <td><code class="small">{{ $product->code }}</code></td>
                        <td class="fw-medium">{{ $product->name }}</td>
                        <td>
                            @if($product->provider)
                            @php $pIcon = provider_icon_url($product->provider->code, $product->provider->logo_url); @endphp
                            <div class="d-flex align-items-center gap-1">
                                @if($pIcon)
                                <img src="{{ $pIcon }}" alt="" width="22" height="22" class="rounded bg-light object-fit-contain">
                                @endif
                                <small>{{ $product->provider->name }}</small>
                            </div>
                            @endif
                        </td>
                        <td><span class="badge bg-{{ match($product->type) { 'ELOAD'=>'success','BILLS'=>'primary','ECASH'=>'danger','WIFI'=>'info',default=>'secondary' } }}">{{ $product->type }}</span></td>
                        <td class="text-end">₱{{ number_format($product->amount, 2) }}</td>
                        <td class="text-end">₱{{ number_format($product->retailer_price ?? 0, 2) }}</td>
                        <td class="text-end text-primary">₱{{ number_format($product->commission ?? 0, 2) }}</td>
                        <td>
                            <span class="badge {{ $product->is_active ? 'text-bg-success' : 'text-bg-danger' }}">{{ $product->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="editProduct({{ $product->toJson() }})"><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="{{ route('epayplus.products.toggle-status', $product) }}" class="d-inline">
                                    @csrf
                                    <button class="btn {{ $product->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}"><i class="bi bi-{{ $product->is_active ? 'pause' : 'play' }}"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No products found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($products->hasPages())
    <div class="card-footer bg-transparent border-0">{{ $products->links() }}</div>
    @endif
</div>

{{-- Product Modal --}}
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="productForm">
                @csrf
                <div id="productMethod"></div>
                <div class="modal-header">
                    <h5 class="modal-title" id="productModalTitle">Add Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Provider <span class="text-danger">*</span></label>
                            <select name="provider_id" id="prProvider" class="form-select" required>
                                @foreach($providers as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->type }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select name="type" id="prType" class="form-select" required>
                                @foreach(['ELOAD','BILLS','ECASH','WIFI','OTHER'] as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="prCode" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="prName" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" name="amount" id="prAmount" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Retailer Price</label>
                            <input type="number" name="retailer_price" id="prRetailerPrice" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fee</label>
                            <input type="number" name="fee" id="prFee" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Commission</label>
                            <input type="number" name="commission" id="prCommission" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Keyword</label>
                            <input type="text" name="keyword" id="prKeyword" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Validity (days)</label>
                            <input type="number" name="validity_days" id="prValidity" class="form-control" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order" id="prSort" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" value="1" id="prActive" class="form-check-input" checked>
                                <label class="form-check-label" for="prActive">Active</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="prDesc" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Import Modal --}}
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('epayplus.products.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Import Products</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Upload a CSV file with columns: ID, Provider, Type, Code, Name, Amount, Retailer Price, Fee, Commission, Active, Keyword</p>
                    <input type="file" name="file" class="form-control" accept=".csv,.txt" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function resetProductForm() {
    document.getElementById('productForm').action = '{{ route("epayplus.products.store") }}';
    document.getElementById('productMethod').innerHTML = '';
    document.getElementById('productModalTitle').textContent = 'Add Product';
    ['prCode','prName','prKeyword','prDesc'].forEach(id => document.getElementById(id).value = '');
    ['prAmount','prRetailerPrice','prFee','prCommission','prSort'].forEach(id => document.getElementById(id).value = '0');
    document.getElementById('prActive').checked = true;
}

function editProduct(p) {
    document.getElementById('productForm').action = '/epayplus/products/' + p.id;
    document.getElementById('productMethod').innerHTML = '@method("PUT")';
    document.getElementById('productModalTitle').textContent = 'Edit Product';
    document.getElementById('prProvider').value = p.provider_id;
    document.getElementById('prType').value = p.type;
    document.getElementById('prCode').value = p.code;
    document.getElementById('prName').value = p.name;
    document.getElementById('prAmount').value = p.amount;
    document.getElementById('prRetailerPrice').value = p.retailer_price || 0;
    document.getElementById('prFee').value = p.fee || 0;
    document.getElementById('prCommission').value = p.commission || 0;
    document.getElementById('prKeyword').value = p.keyword || '';
    document.getElementById('prValidity').value = p.validity_days || '';
    document.getElementById('prSort').value = p.sort_order || 0;
    document.getElementById('prActive').checked = p.is_active;
    document.getElementById('prDesc').value = p.description || '';
    new bootstrap.Modal(document.getElementById('productModal')).show();
}
</script>
@endpush
