@extends('layouts.epayplus')

@section('title', 'POS Mode')

@push('styles')
<style>
    :root { --epay-green: #2E7D32; }
    .pos-service-card { border: 2px solid #e8f5e9; transition: .15s; cursor: pointer; }
    .pos-service-card:hover { border-color: var(--epay-green); background: #f1f8e9; }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-success">POS Mode</h4>
        <small class="text-muted">Quick access to ePay Plus services</small>
    </div>
    <form method="get" class="d-flex gap-2 align-items-center">
        <select name="retailer_id" class="form-select form-select-sm" style="min-width:220px" onchange="this.form.submit()">
            @foreach($retailers as $r)
                <option value="{{ $r->id }}" @selected($retailerId == $r->id)>{{ $r->business_name }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">ePay Plus Services</div>
    <div class="card-body">
        <div class="row g-2">
            @foreach([
                ['eload', 'E-Load', 'bi-phone', route('epayplus.products', ['type' => 'ELOAD'])],
                ['bills', 'Bills Payment', 'bi-receipt', route('epayplus.products', ['type' => 'BILLS'])],
                ['ecash', 'Cash-in', 'bi-wallet2', route('epayplus.products', ['type' => 'ECASH'])],
                ['rfid', 'RFID', 'bi-credit-card', route('epayplus.products', ['type' => 'RFID'])],
            ] as [$key, $label, $icon, $href])
            <div class="col-6 col-md-3">
                <a href="{{ $href }}" class="text-decoration-none text-dark">
                    <div class="pos-service-card rounded-3 p-3 text-center h-100">
                        <i class="bi {{ $icon }} fs-3 text-success"></i>
                        <div class="small fw-semibold mt-2">{{ $label }}</div>
                        <div class="text-muted" style="font-size:.7rem">Use app for txn</div>
                    </div>
                </a>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
