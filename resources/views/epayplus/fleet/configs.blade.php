@extends('layouts.epayplus')
@section('title', 'Remote Config Profiles')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Remote Config Profiles</h4>
        <small class="text-muted">Service toggles pushed to fleet devices via heartbeat</small>
    </div>
    <a href="{{ route('epayplus.fleet.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Fleet</a>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">New Profile</div>
            <div class="card-body">
                <form method="POST" action="{{ route('epayplus.fleet.configs.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <p class="small text-muted mb-2">Enabled Services</p>
                    @foreach(['eload'=>'E-Load','bills'=>'Bills','gcash'=>'GCash','maya'=>'Maya','ecash'=>'E-Cash'] as $key => $label)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="{{ $key }}" id="new_{{ $key }}" checked>
                        <label class="form-check-label" for="new_{{ $key }}">{{ $label }}</label>
                    </div>
                    @endforeach
                    <button class="btn btn-success w-100 mt-3">Create Profile</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        @foreach($configs as $config)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <div>
                    <span class="fw-semibold">{{ $config->name }}</span>
                    @if($config->is_default)<span class="badge bg-primary ms-2">Default</span>@endif
                    <small class="text-muted d-block">{{ $config->description }}</small>
                </div>
                <span class="badge bg-secondary">{{ $config->devices_count }} devices</span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('epayplus.fleet.configs.update', $config) }}">
                    @csrf @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <input type="text" name="name" class="form-control form-control-sm" value="{{ $config->name }}">
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="description" class="form-control form-control-sm" value="{{ $config->description }}" placeholder="Description">
                        </div>
                        @php $svc = $config->settings['services'] ?? []; @endphp
                        @foreach(['eload','bills','gcash','maya','ecash'] as $key)
                        <div class="col-auto">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="{{ $key }}" id="{{ $config->id }}_{{ $key }}"
                                    {{ ($svc[$key] ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label small" for="{{ $config->id }}_{{ $key }}">{{ strtoupper($key) }}</label>
                            </div>
                        </div>
                        @endforeach
                        <div class="col-12">
                            <button class="btn btn-sm btn-primary">Save</button>
                            @if(!$config->is_default)
                            <button type="submit" formaction="{{ route('epayplus.fleet.configs.delete', $config) }}" formmethod="POST"
                                class="btn btn-sm btn-outline-danger ms-2" onclick="this.form.querySelector('[name=_method]').value='DELETE'; return confirm('Delete profile?')">
                                Delete
                            </button>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection
