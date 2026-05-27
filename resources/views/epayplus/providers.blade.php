@extends('layouts.epayplus')

@section('title', 'Providers')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Providers</h4>
        <small class="text-muted">{{ $providers->count() }} providers configured</small>
    </div>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#providerModal" onclick="resetProviderForm()">
        <i class="bi bi-plus-lg"></i> Add Provider
    </button>
</div>

<div class="row g-3">
    @forelse($providers as $provider)
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="d-flex align-items-center gap-2">
                        @php $iconUrl = provider_icon_url($provider->code, $provider->logo_url); @endphp
                        @if($iconUrl)
                        <img src="{{ $iconUrl }}" alt="{{ $provider->name }}" width="40" height="40" class="rounded-circle bg-light object-fit-contain p-1">
                        @else
                        <span class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px">
                            <i class="bi bi-building text-muted"></i>
                        </span>
                        @endif
                        <div>
                        <h6 class="fw-bold mb-0">{{ $provider->name }}</h6>
                        <code class="small">{{ $provider->code }}</code>
                        </div>
                    </div>
                    <span class="badge {{ $provider->is_active ? 'text-bg-success' : 'text-bg-danger' }}">
                        {{ $provider->is_active ? 'Active' : 'Disabled' }}
                    </span>
                </div>
                <div class="mb-2">
                    <span class="badge bg-{{ match($provider->type) { 'ELOAD'=>'success','BILLS'=>'primary','ECASH'=>'danger','RFID'=>'warning','WIFI'=>'info',default=>'secondary' } }}">{{ $provider->type }}</span>
                    @if($provider->billing_type)
                    <span class="badge bg-{{ $provider->billing_type === 'prepaid' ? 'info' : 'dark' }}">{{ ucfirst($provider->billing_type) }}</span>
                    @endif
                    @if($provider->category)
                    <span class="badge bg-light text-dark">{{ $provider->category }}</span>
                    @endif
                </div>
                <div class="small text-muted mb-2">
                    <i class="bi bi-box-seam"></i> {{ $provider->products_count }} products ({{ $provider->active_products_count }} active)
                </div>
                @if($provider->sms_number)
                <div class="small text-muted"><i class="bi bi-phone"></i> SMS: {{ $provider->sms_number }}</div>
                @endif
            </div>
            <div class="card-footer bg-transparent border-0 d-flex gap-1">
                <button class="btn btn-sm btn-outline-primary flex-fill" onclick="editProvider({{ $provider->toJson() }})">
                    <i class="bi bi-pencil"></i> Edit
                </button>
                <form method="POST" action="{{ route('epayplus.providers.toggle-status', $provider) }}">
                    @csrf
                    <button class="btn btn-sm {{ $provider->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                        <i class="bi bi-{{ $provider->is_active ? 'pause' : 'play' }}"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="text-center text-muted py-5">
            <i class="bi bi-building fs-1"></i>
            <p class="mt-2">No providers yet. Add your first provider to get started.</p>
        </div>
    </div>
    @endforelse
</div>

{{-- Provider Modal --}}
<div class="modal fade" id="providerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="providerForm">
                @csrf
                <div id="providerMethod"></div>
                <div class="modal-header">
                    <h5 class="modal-title" id="providerModalTitle">Add Provider</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-8">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="pName" class="form-control" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="pCode" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select name="type" id="pType" class="form-select" required>
                                <option value="ELOAD">E-Load</option>
                                <option value="BILLS">Bills Payment</option>
                                <option value="ECASH">E-Cash</option>
                                <option value="WIFI">WiFi</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" id="pCategory" class="form-control" placeholder="e.g. Electricity, Water">
                        </div>
                        <div class="col-6">
                            <label class="form-label">SMS Number</label>
                            <input type="text" name="sms_number" id="pSms" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order" id="pSort" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">SMS Format</label>
                            <textarea name="sms_format" id="pSmsFormat" class="form-control" rows="2" placeholder="e.g. {keyword} {amount} {number}"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" value="1" id="pActive" class="form-check-input" checked>
                                <label class="form-check-label" for="pActive">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Provider</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function resetProviderForm() {
    document.getElementById('providerForm').action = '{{ route("epayplus.providers.store") }}';
    document.getElementById('providerMethod').innerHTML = '';
    document.getElementById('providerModalTitle').textContent = 'Add Provider';
    ['pName','pCode','pCategory','pSms','pSmsFormat'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('pType').value = 'ELOAD';
    document.getElementById('pSort').value = '0';
    document.getElementById('pActive').checked = true;
}

function editProvider(p) {
    document.getElementById('providerForm').action = '/epayplus/providers/' + p.id;
    document.getElementById('providerMethod').innerHTML = '@method("PUT")';
    document.getElementById('providerModalTitle').textContent = 'Edit Provider';
    document.getElementById('pName').value = p.name;
    document.getElementById('pCode').value = p.code;
    document.getElementById('pType').value = p.type;
    document.getElementById('pCategory').value = p.category || '';
    document.getElementById('pSms').value = p.sms_number || '';
    document.getElementById('pSmsFormat').value = p.sms_format || '';
    document.getElementById('pSort').value = p.sort_order || 0;
    document.getElementById('pActive').checked = p.is_active;
    new bootstrap.Modal(document.getElementById('providerModal')).show();
}
</script>
@endpush
