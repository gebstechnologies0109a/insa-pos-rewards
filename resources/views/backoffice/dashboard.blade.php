@extends('layouts.backoffice')

@section('page-title', 'Dashboard')

@section('content')
<!-- Branch Selector -->
<div class="mb-6">
    <form method="GET" action="{{ route('backoffice.dashboard') }}" class="inline-flex">
        <x-branch-selector :branch-id="$branchId" :branches="$branches" />
    </form>
</div>

<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <x-dashboard.card title="Total Products" :value="$totalProducts" />
    <x-dashboard.card title="Sales Today" :value="$salesToday" />
    <x-dashboard.card title="Revenue Today" :value="'₱' . number_format($revenueToday, 2)" />
    <x-dashboard.card title="Low Stock" :value="$lowStockCount" tone="warning" />
    <x-dashboard.card title="Out of Stock" :value="$outOfStockCount" tone="danger" />
</div>

@include('backoffice.partials.shifts-summary', ['shiftSummary' => $shiftSummary])

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    @include('backoffice.partials.recent-sales', ['recentSales' => $recentSales])

    <x-sidebar.quick-links :branches="$branches" :total-users="$totalUsers" />
</div>
@endsection
