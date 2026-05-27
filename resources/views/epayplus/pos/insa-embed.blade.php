@extends('layouts.epayplus')

@section('title', 'INSA POS')

@push('styles')
<style>
    .insa-pos-embed-wrap {
        min-height: calc(100vh - 100px);
        display: flex;
        flex-direction: column;
    }
    .insa-pos-embed-frame {
        flex: 1;
        width: 100%;
        min-height: 72vh;
        border: 1px solid #dee2e6;
        border-radius: .5rem;
        background: #fff;
    }
</style>
@endpush

@section('content')
{{--
    Auth: ePay Plus admin session ≠ INSA web session. User signs in inside the iframe on insapos.
    Do not point iframe at epayplus host /pos/cashier (404 on epayplus product).
--}}
<div class="insa-pos-embed-wrap">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0 text-success">INSA POS</h4>
            <small class="text-muted">Retail cashier on INSA — separate login from ePay Plus admin</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ $insaUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-success btn-sm">
                <i class="bi bi-box-arrow-up-right"></i> Open INSA POS in new tab
            </a>
            <a href="{{ route('epayplus.pos', ['epay' => 1]) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-phone"></i> ePay service POS
            </a>
        </div>
    </div>

    <iframe
        class="insa-pos-embed-frame"
        src="{{ $insaUrl }}"
        title="INSA POS Cashier"
        allow="payment *; clipboard-read; clipboard-write"
        referrerpolicy="strict-origin-when-cross-origin"
    ></iframe>

    <p class="text-muted small mt-2 mb-0">
        If the cashier does not load, confirm <code>APP_PRODUCT=insa</code> on the INSA server and use
        <a href="{{ $insaUrl }}" target="_blank" rel="noopener">insapos</a> directly.
    </p>
</div>
@endsection
