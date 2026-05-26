@extends('layouts.epayplus')

@section('title', 'Commission Report')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Commission Report</h4>
        <small class="text-muted">{{ $dateFrom }} to {{ $dateTo }}</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('epayplus.reports.export', ['type' => 'commissions', 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download"></i> Export CSV</a>
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

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fw-bold fs-3 text-success">₱{{ number_format($totals['amount'], 2) }}</div>
            <small class="text-muted">Total Sales</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fw-bold fs-3 text-primary">₱{{ number_format($totals['commission'], 2) }}</div>
            <small class="text-muted">Total Commission</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fw-bold fs-3">{{ number_format($totals['count']) }}</div>
            <small class="text-muted">Total Transactions</small>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Type</th><th>Provider</th><th class="text-end">Transactions</th><th class="text-end">Sales</th><th class="text-end">Commission</th><th class="text-end">Rate</th></tr>
                </thead>
                <tbody>
                    @forelse($commissions as $c)
                    <tr>
                        <td><span class="badge bg-{{ match($c->type) { 'ELOAD'=>'success','BILLS'=>'primary','ECASH'=>'danger','WIFI'=>'info',default=>'secondary' } }}">{{ $c->type }}</span></td>
                        <td>{{ $c->provider_code }}</td>
                        <td class="text-end">{{ number_format($c->count) }}</td>
                        <td class="text-end fw-bold">₱{{ number_format($c->total_amount, 2) }}</td>
                        <td class="text-end text-primary fw-bold">₱{{ number_format($c->total_commission, 2) }}</td>
                        <td class="text-end text-muted">{{ $c->total_amount > 0 ? number_format(($c->total_commission / $c->total_amount) * 100, 2) : 0 }}%</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
