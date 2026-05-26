@extends('layouts.epayplus')

@section('title', 'Daily Sales Report')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Daily Sales Report</h4>
        <small class="text-muted">{{ $dateFrom }} to {{ $dateTo }}</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('epayplus.reports.export', ['type' => 'daily-sales', 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-download"></i> Export CSV</a>
        <a href="{{ route('epayplus.reports') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-0">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
            </div>
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
                        <th>Date</th>
                        <th class="text-end">Transactions</th>
                        <th class="text-end">Sales Amount</th>
                        <th class="text-end">Commission</th>
                        <th class="text-end">Fees</th>
                    </tr>
                </thead>
                <tbody>
                    @php $grandTotal = ['count' => 0, 'amount' => 0, 'commission' => 0, 'fee' => 0]; @endphp
                    @forelse($dailyTotals as $date => $day)
                    <tr>
                        <td class="fw-medium">{{ \Carbon\Carbon::parse($date)->format('M d, Y (D)') }}</td>
                        <td class="text-end">{{ number_format($day['count']) }}</td>
                        <td class="text-end fw-bold text-success">₱{{ number_format($day['amount'], 2) }}</td>
                        <td class="text-end text-primary">₱{{ number_format($day['commission'], 2) }}</td>
                        <td class="text-end">₱{{ number_format($day['fee'], 2) }}</td>
                    </tr>
                    @php
                        $grandTotal['count'] += $day['count'];
                        $grandTotal['amount'] += $day['amount'];
                        $grandTotal['commission'] += $day['commission'];
                        $grandTotal['fee'] += $day['fee'];
                    @endphp
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No data for this period.</td></tr>
                    @endforelse
                </tbody>
                @if($dailyTotals->count() > 0)
                <tfoot class="table-dark">
                    <tr>
                        <td class="fw-bold">TOTAL</td>
                        <td class="text-end fw-bold">{{ number_format($grandTotal['count']) }}</td>
                        <td class="text-end fw-bold">₱{{ number_format($grandTotal['amount'], 2) }}</td>
                        <td class="text-end fw-bold">₱{{ number_format($grandTotal['commission'], 2) }}</td>
                        <td class="text-end fw-bold">₱{{ number_format($grandTotal['fee'], 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
