@extends('layouts.epayplus')

@section('title', $retailer ? 'Edit Retailer' : 'Add Retailer')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">{{ $retailer ? 'Edit Retailer' : 'Add New Retailer' }}</h4>
        <small class="text-muted">{{ $retailer ? $retailer->business_name : 'Fill in details below' }}</small>
    </div>
    <a href="{{ $retailer ? route('epayplus.retailers.show', $retailer) : route('epayplus.retailers') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ $retailer ? route('epayplus.retailers.update', $retailer) : route('epayplus.retailers.store') }}">
                    @csrf
                    @if($retailer) @method('PUT') @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Business Name <span class="text-danger">*</span></label>
                            <input type="text" name="business_name" class="form-control @error('business_name') is-invalid @enderror"
                                   value="{{ old('business_name', $retailer?->business_name) }}" required>
                            @error('business_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Owner Name <span class="text-danger">*</span></label>
                            <input type="text" name="owner_name" class="form-control @error('owner_name') is-invalid @enderror"
                                   value="{{ old('owner_name', $retailer?->owner_name) }}" required>
                            @error('owner_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Mobile Number <span class="text-danger">*</span></label>
                            <input type="text" name="mobile_number" class="form-control @error('mobile_number') is-invalid @enderror"
                                   value="{{ old('mobile_number', $retailer?->mobile_number) }}" required>
                            @error('mobile_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Email</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email', $retailer?->email) }}">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Address</label>
                            <textarea name="address" class="form-control" rows="2">{{ old('address', $retailer?->address) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Credit Limit</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" name="credit_limit" class="form-control" step="0.01" min="0"
                                       value="{{ old('credit_limit', $retailer?->credit_limit ?? 0) }}">
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-lg"></i> {{ $retailer ? 'Update Retailer' : 'Create Retailer' }}
                        </button>
                        <a href="{{ $retailer ? route('epayplus.retailers.show', $retailer) : route('epayplus.retailers') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
