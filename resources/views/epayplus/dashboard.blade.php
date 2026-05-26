@extends('layouts.epayplus')

@section('title', 'Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Dashboard</h4>
        <small class="text-muted">ePayPlus Admin Overview</small>
    </div>
    <span class="badge bg-success fs-6">v3.0</span>
</div>

{{-- Stats Cards Row 1 --}}
<div class="row g-3 mb-3">
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted mb-1 small">Today's Sales</p>
                        <h4 class="fw-bold text-success mb-0">₱{{ number_format($stats['todaySales'], 2) }}</h4>
                    </div>
                    <div class="bg-success bg-opacity-10 rounded-3 p-2 align-self-start">
                        <i class="bi bi-graph-up text-success fs-4"></i>
                    </div>
                </div>
                <small class="text-muted">{{ $stats['todayTransactions'] }} transactions</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted mb-1 small">Commissions</p>
                        <h4 class="fw-bold text-primary mb-0">₱{{ number_format($stats['todayCommissions'], 2) }}</h4>
                    </div>
                    <div class="bg-primary bg-opacity-10 rounded-3 p-2 align-self-start">
                        <i class="bi bi-cash-stack text-primary fs-4"></i>
                    </div>
                </div>
                <small class="text-muted">Today's profit</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted mb-1 small">Active Retailers</p>
                        <h4 class="fw-bold mb-0">{{ $stats['activeRetailers'] }}</h4>
                    </div>
                    <div class="bg-info bg-opacity-10 rounded-3 p-2 align-self-start">
                        <i class="bi bi-people text-info fs-4"></i>
                    </div>
                </div>
                <small class="text-muted">of {{ $stats['totalRetailers'] }} total</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted mb-1 small">Pending Top-ups</p>
                        <h4 class="fw-bold text-warning mb-0">{{ $stats['pendingTopups'] }}</h4>
                    </div>
                    <div class="bg-warning bg-opacity-10 rounded-3 p-2 align-self-start">
                        <i class="bi bi-clock text-warning fs-4"></i>
                    </div>
                </div>
                <a href="{{ route('epayplus.topups') }}" class="small text-warning text-decoration-none">View requests &rarr;</a>
            </div>
        </div>
    </div>
</div>

{{-- Stats Cards Row 2 --}}
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center">
                    <i class="bi bi-building text-secondary fs-4 me-3"></i>
                    <div>
                        <small class="text-muted">Providers</small>
                        <div class="fw-bold">{{ $stats['activeProviders'] }}/{{ $stats['totalProviders'] }} active</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center">
                    <i class="bi bi-box-seam text-secondary fs-4 me-3"></i>
                    <div>
                        <small class="text-muted">Products</small>
                        <div class="fw-bold">{{ $stats['totalProducts'] }} total</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle text-danger fs-4 me-3"></i>
                    <div>
                        <small class="text-muted">Failed Today</small>
                        <div class="fw-bold text-danger">{{ $stats['failedToday'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center">
                    <i class="bi bi-hourglass-split text-warning fs-4 me-3"></i>
                    <div>
                        <small class="text-muted">Processing</small>
                        <div class="fw-bold text-warning">{{ $stats['processingCount'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Charts --}}
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="bi bi-graph-up"></i> Revenue Trends</h6>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-success active" data-range="7days">7 Days</button>
                    <button class="btn btn-outline-success" data-range="30days">30 Days</button>
                    <button class="btn btn-outline-success" data-range="90days">90 Days</button>
                </div>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0">
                <h6 class="fw-bold mb-0"><i class="bi bi-pie-chart"></i> By Type</h6>
            </div>
            <div class="card-body">
                <canvas id="typeChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Pending Top-ups --}}
    @if($pendingTopups->count() > 0)
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning bg-opacity-10 border-0 d-flex justify-content-between">
                <h6 class="fw-bold mb-0"><i class="bi bi-clock"></i> Pending Top-ups</h6>
                <a href="{{ route('epayplus.topups') }}" class="btn btn-sm btn-outline-warning">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Retailer</th><th>Amount</th><th>Method</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            @foreach($pendingTopups->take(5) as $topup)
                            <tr>
                                <td>
                                    <small class="fw-medium">{{ $topup->retailer->business_name }}</small><br>
                                    <small class="text-muted">{{ $topup->created_at->diffForHumans() }}</small>
                                </td>
                                <td class="fw-bold">₱{{ number_format($topup->amount, 2) }}</td>
                                <td><span class="badge bg-secondary">{{ $topup->payment_method }}</span></td>
                                <td>
                                    <form action="{{ route('epayplus.topups.approve', $topup) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-success"><i class="bi bi-check"></i></button>
                                    </form>
                                    <form action="{{ route('epayplus.topups.reject', $topup) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-danger"><i class="bi bi-x"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Recent Transactions --}}
    <div class="{{ $pendingTopups->count() > 0 ? 'col-lg-7' : 'col-12' }}">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between">
                <h6 class="fw-bold mb-0"><i class="bi bi-list-check"></i> Recent Transactions</h6>
                <a href="{{ route('epayplus.transactions') }}" class="btn btn-sm btn-outline-success">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Ref #</th><th>Retailer</th><th>Type</th><th>Amount</th><th>Status</th><th>Time</th></tr>
                        </thead>
                        <tbody>
                            @foreach($recentTransactions->take(10) as $txn)
                            <tr>
                                <td><a href="{{ route('epayplus.transactions.show', $txn) }}" class="text-decoration-none"><code class="small">{{ $txn->reference_number }}</code></a></td>
                                <td><small>{{ $txn->retailer?->business_name ?? 'N/A' }}</small></td>
                                <td>
                                    @php $typeBadge = match($txn->type) { 'ELOAD'=>'bg-success','BILLS'=>'bg-primary','ECASH'=>'bg-danger','WIFI'=>'bg-info', default=>'bg-secondary' }; @endphp
                                    <span class="badge {{ $typeBadge }}">{{ $txn->type }}</span>
                                </td>
                                <td class="fw-medium">₱{{ number_format($txn->amount, 2) }}</td>
                                <td>
                                    @php $sBadge = match($txn->status) { 'SUCCESS'=>'text-bg-success','FAILED'=>'text-bg-danger','PROCESSING'=>'text-bg-warning', default=>'text-bg-secondary' }; @endphp
                                    <span class="badge {{ $sBadge }}">{{ $txn->status }}</span>
                                </td>
                                <td><small class="text-muted">{{ $txn->created_at->format('H:i') }}</small></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Monthly Summary --}}
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <span class="text-muted">This Month:</span>
                <strong>{{ number_format($stats['monthTransactions']) }} transactions</strong> &bull;
                <strong class="text-success">₱{{ number_format($stats['monthSales'], 2) }} total sales</strong> &bull;
                <strong class="text-primary">₱{{ number_format($stats['totalBalance'], 2) }} retailer balances</strong>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
let revenueChart, typeChart;

function loadChartData(range) {
    fetch('{{ route("epayplus.dashboard.chart") }}?range=' + range)
        .then(r => r.json())
        .then(data => {
            if (revenueChart) revenueChart.destroy();
            if (typeChart) typeChart.destroy();

            revenueChart = new Chart(document.getElementById('revenueChart'), {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        { label: 'Sales', data: data.sales, borderColor: '#198754', backgroundColor: 'rgba(25,135,84,0.1)', fill: true, tension: 0.3 },
                        { label: 'Commission', data: data.commissions, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.1)', fill: true, tension: 0.3 }
                    ]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
            });

            typeChart = new Chart(document.getElementById('typeChart'), {
                type: 'doughnut',
                data: {
                    labels: data.typeLabels,
                    datasets: [{ data: data.typeTotals, backgroundColor: ['#198754','#0d6efd','#dc3545','#0dcaf0','#6c757d'] }]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
            });
        });
}

document.querySelectorAll('[data-range]').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('[data-range]').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        loadChartData(this.dataset.range);
    });
});

loadChartData('7days');
</script>
@endpush
