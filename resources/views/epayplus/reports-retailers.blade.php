@extends('layouts.epayplus')

@section('title', 'Retailer Performance')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Retailer Performance</h4>
        <small class="text-muted">{{ $dateFrom }} to {{ $dateTo }}</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('epayplus.reports.export', ['type' => 'retailer-performance', 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download"></i> Export CSV</a>
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
                    <tr>
                        <th>#</th>
                        <th>Retailer</th>
                        <th>Owner</th>
                        <th>Balance</th>
                        <th class="text-end">Transactions</th>
                        <th class="text-end">Sales</th>
                        <th class="text-end">Commission</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($retailers as $i => $r)
                    <tr>
                        <td class="text-muted">{{ $retailers->firstItem() + $i }}</td>
                        <td class="fw-medium">{{ $r->business_name }}</td>
                        <td><small>{{ $r->owner_name }}</small></td>
                        <td class="text-success">₱{{ number_format($r->balance, 2) }}</td>
                        <td class="text-end">{{ number_format($r->period_txn_count ?? 0) }}</td>
                        <td class="text-end fw-bold">₱{{ number_format($r->period_sales ?? 0, 2) }}</td>
                        <td class="text-end text-primary">₱{{ number_format($r->period_commission ?? 0, 2) }}</td>
                        <td><a href="{{ route('epayplus.retailers.show', $r) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-eye"></i></a></td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($retailers->hasPages())
    <div class="card-footer bg-transparent border-0">{{ $retailers->links() }}</div>
    @endif
</div>
@endsection
