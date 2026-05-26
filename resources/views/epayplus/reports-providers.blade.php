@extends('layouts.epayplus')

@section('title', 'Provider Performance')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Provider Performance</h4>
        <small class="text-muted">{{ $dateFrom }} to {{ $dateTo }}</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('epayplus.reports.export', ['type' => 'provider-performance', 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download"></i> Export CSV</a>
        <a href="{{ route('epayplus.reports') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto"><input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}"></div>
            <div class="col-auto"><input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}"></div>
            <div class="col-auto"><button class="btn btn-sm btn-success">Apply</button></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Provider</th><th>Type</th><th class="text-end">Transactions</th><th class="text-end">Sales</th><th class="text-end">Commission</th><th class="text-end">Fees</th></tr>
                </thead>
                <tbody>
                    @php $totals = ['count' => 0, 'amount' => 0, 'commission' => 0, 'fee' => 0]; @endphp
                    @forelse($providers as $p)
                    <tr>
                        <td class="fw-medium">{{ $p->provider_code }}</td>
                        <td><span class="badge bg-{{ match($p->type) { 'ELOAD'=>'success','BILLS'=>'primary','ECASH'=>'danger','WIFI'=>'info',default=>'secondary' } }}">{{ $p->type }}</span></td>
                        <td class="text-end">{{ number_format($p->count) }}</td>
                        <td class="text-end fw-bold">₱{{ number_format($p->total_amount, 2) }}</td>
                        <td class="text-end text-primary">₱{{ number_format($p->total_commission, 2) }}</td>
                        <td class="text-end">₱{{ number_format($p->total_fee, 2) }}</td>
                    </tr>
                    @php
                        $totals['count'] += $p->count;
                        $totals['amount'] += $p->total_amount;
                        $totals['commission'] += $p->total_commission;
                        $totals['fee'] += $p->total_fee;
                    @endphp
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No data.</td></tr>
                    @endforelse
                </tbody>
                @if($providers->count() > 0)
                <tfoot class="table-dark">
                    <tr>
                        <td colspan="2" class="fw-bold">TOTAL</td>
                        <td class="text-end fw-bold">{{ number_format($totals['count']) }}</td>
                        <td class="text-end fw-bold">₱{{ number_format($totals['amount'], 2) }}</td>
                        <td class="text-end fw-bold">₱{{ number_format($totals['commission'], 2) }}</td>
                        <td class="text-end fw-bold">₱{{ number_format($totals['fee'], 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
