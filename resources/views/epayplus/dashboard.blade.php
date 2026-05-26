@extends('layouts.admin')

@section('title', 'ePayPlus Dashboard')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-success mb-0">ePayPlus Admin</h2>
            <small class="text-muted">Server: epayplus.diybizrewards.com</small>
        </div>
        <span class="badge bg-success fs-6">v2.0</span>
    </div>

    {{-- Stats Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-muted mb-1 small">Today's Sales</p>
                            <h4 class="fw-bold text-success">₱{{ number_format($stats['todaySales'], 2) }}</h4>
                        </div>
                        <div class="bg-success bg-opacity-10 rounded-3 p-2 align-self-start">
                            <i class="bi bi-graph-up text-success fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">{{ $stats['todayTransactions'] }} transactions</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-muted mb-1 small">Commissions</p>
                            <h4 class="fw-bold text-primary">₱{{ number_format($stats['todayCommissions'], 2) }}</h4>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded-3 p-2 align-self-start">
                            <i class="bi bi-cash-stack text-primary fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">Today's profit</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-muted mb-1 small">Active Retailers</p>
                            <h4 class="fw-bold">{{ $stats['activeRetailers'] }}</h4>
                        </div>
                        <div class="bg-info bg-opacity-10 rounded-3 p-2 align-self-start">
                            <i class="bi bi-people text-info fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">of {{ $stats['totalRetailers'] }} total</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-muted mb-1 small">Pending Top-ups</p>
                            <h4 class="fw-bold text-warning">{{ $stats['pendingTopups'] }}</h4>
                        </div>
                        <div class="bg-warning bg-opacity-10 rounded-3 p-2 align-self-start">
                            <i class="bi bi-clock text-warning fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">Awaiting approval</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Pending Top-ups --}}
        @if($pendingTopups->count() > 0)
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-warning bg-opacity-10 border-0">
                    <h6 class="fw-bold mb-0"><i class="bi bi-clock"></i> Pending Top-up Requests</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Retailer</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pendingTopups as $topup)
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
                                            <button class="btn btn-sm btn-success">✓</button>
                                        </form>
                                        <form action="{{ route('epayplus.topups.reject', $topup) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-danger">✗</button>
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
                                <tr>
                                    <th>Ref #</th>
                                    <th>Retailer</th>
                                    <th>Type</th>
                                    <th>Target</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentTransactions as $txn)
                                <tr>
                                    <td><code class="small">{{ $txn->reference_number }}</code></td>
                                    <td><small>{{ $txn->retailer?->business_name ?? 'N/A' }}</small></td>
                                    <td>
                                        @php
                                            $typeBadge = match($txn->type) {
                                                'ELOAD' => 'bg-success',
                                                'BILLS' => 'bg-primary',
                                                'ECASH' => 'bg-danger',
                                                'WIFI' => 'bg-info',
                                                default => 'bg-secondary',
                                            };
                                        @endphp
                                        <span class="badge {{ $typeBadge }}">{{ $txn->type }}</span>
                                    </td>
                                    <td><small>{{ $txn->target_number }}</small></td>
                                    <td class="fw-medium">₱{{ number_format($txn->amount, 2) }}</td>
                                    <td>
                                        @php
                                            $statusBadge = match($txn->status) {
                                                'SUCCESS' => 'text-bg-success',
                                                'FAILED' => 'text-bg-danger',
                                                'PROCESSING' => 'text-bg-warning',
                                                default => 'text-bg-secondary',
                                            };
                                        @endphp
                                        <span class="badge {{ $statusBadge }}">{{ $txn->status }}</span>
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
                    <strong>{{ number_format($stats['monthTransactions']) }} transactions</strong> •
                    <strong class="text-success">₱{{ number_format($stats['monthSales'], 2) }} total sales</strong> •
                    <strong class="text-primary">₱{{ number_format($stats['totalBalance'], 2) }} retailer balances</strong>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
