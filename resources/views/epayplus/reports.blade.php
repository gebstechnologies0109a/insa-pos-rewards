@extends('layouts.epayplus')

@section('title', 'Reports')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">Reports</h4>
    <small class="text-muted">Generate and export business reports</small>
</div>

<div class="row g-4">
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-4">
                <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:60px;height:60px">
                    <i class="bi bi-calendar-check text-success fs-3"></i>
                </div>
                <h6 class="fw-bold">Daily Sales</h6>
                <p class="small text-muted">Daily sales breakdown by transaction type with totals</p>
                <a href="{{ route('epayplus.reports.daily-sales') }}" class="btn btn-success btn-sm w-100">View Report</a>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-4">
                <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:60px;height:60px">
                    <i class="bi bi-cash-stack text-primary fs-3"></i>
                </div>
                <h6 class="fw-bold">Commissions</h6>
                <p class="small text-muted">Commission earned by type and provider</p>
                <a href="{{ route('epayplus.reports.commissions') }}" class="btn btn-primary btn-sm w-100">View Report</a>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-4">
                <div class="bg-info bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:60px;height:60px">
                    <i class="bi bi-people text-info fs-3"></i>
                </div>
                <h6 class="fw-bold">Retailer Performance</h6>
                <p class="small text-muted">Top retailers ranked by sales volume</p>
                <a href="{{ route('epayplus.reports.retailer-performance') }}" class="btn btn-info btn-sm w-100 text-white">View Report</a>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-4">
                <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:60px;height:60px">
                    <i class="bi bi-building text-warning fs-3"></i>
                </div>
                <h6 class="fw-bold">Provider Performance</h6>
                <p class="small text-muted">Provider usage and revenue metrics</p>
                <a href="{{ route('epayplus.reports.provider-performance') }}" class="btn btn-warning btn-sm w-100">View Report</a>
            </div>
        </div>
    </div>
</div>
@endsection
