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
    <div class="bg-white rounded-lg shadow p-5">
        <div class="text-sm text-gray-500">Total Products</div>
        <div class="text-3xl font-bold mt-1">{{ $totalProducts }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="text-sm text-gray-500">Sales Today</div>
        <div class="text-3xl font-bold mt-1">{{ $salesToday }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="text-sm text-gray-500">Revenue Today</div>
        <div class="text-3xl font-bold mt-1">&#8369;{{ number_format($revenueToday, 2) }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="text-sm text-gray-500">Low Stock</div>
        <div class="text-3xl font-bold mt-1 text-yellow-600">{{ $lowStockCount }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <div class="text-sm text-gray-500">Out of Stock</div>
        <div class="text-3xl font-bold mt-1 text-red-600">{{ $outOfStockCount }}</div>
    </div>
</div>

<!-- Shift Summary Widget -->
@include('backoffice.widgets.shift-summary')

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Recent Sales -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="font-semibold text-lg mb-4">Recent Sales</h3>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b">
                    <th class="text-left py-2">Sale #</th>
                    <th class="text-right py-2">Total</th>
                    <th class="text-right py-2">Time</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentSales as $sale)
                <tr class="border-b">
                    <td class="py-2 font-mono text-xs">{{ $sale->sale_number }}</td>
                    <td class="py-2 text-right">&#8369;{{ number_format($sale->total, 2) }}</td>
                    <td class="py-2 text-right text-gray-500 text-xs">{{ $sale->sold_at?->diffForHumans() ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="py-4 text-center text-gray-400">No sales yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Quick Links -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="font-semibold text-lg mb-4">Quick Links</h3>
        <div class="grid grid-cols-2 gap-3">
            @if(auth()->user()->isSuperAdmin())
            <a href="{{ route('super-admin.dashboard') }}" class="p-4 bg-indigo-50 rounded hover:bg-indigo-100 text-center col-span-2 border border-indigo-200">
                <div class="font-semibold text-indigo-900">Super Admin Panel</div>
                <div class="text-xs text-indigo-600 mt-1">Licenses, branches &amp; POS sessions</div>
            </a>
            @endif
            <a href="{{ route('admin.products.index') }}" class="p-4 bg-blue-50 rounded hover:bg-blue-100 text-center">
                <div class="font-medium text-blue-800">Products</div>
                <div class="text-xs text-blue-600 mt-1">Manage catalog</div>
            </a>
            <a href="{{ route('admin.inventory.dashboard') }}" class="p-4 bg-green-50 rounded hover:bg-green-100 text-center">
                <div class="font-medium text-green-800">Inventory</div>
                <div class="text-xs text-green-600 mt-1">Stock levels</div>
            </a>
            @if(auth()->user()->canManageUsers())
            <a href="{{ route('admin.users.index') }}" class="p-4 bg-purple-50 rounded hover:bg-purple-100 text-center">
                <div class="font-medium text-purple-800">Users</div>
                <div class="text-xs text-purple-600 mt-1">Manage accounts</div>
            </a>
            <a href="{{ route('admin.branches.index') }}" class="p-4 bg-indigo-50 rounded hover:bg-indigo-100 text-center">
                <div class="font-medium text-indigo-800">Branches</div>
                <div class="text-xs text-indigo-600 mt-1">Manage locations</div>
            </a>
            @endif
            <a href="{{ route('pos.cashier') }}" class="p-4 bg-yellow-50 rounded hover:bg-yellow-100 text-center">
                <div class="font-medium text-yellow-800">POS Cashier</div>
                <div class="text-xs text-yellow-600 mt-1">Open register</div>
            </a>
            <a href="{{ route('stockman.stock-in') }}" class="p-4 bg-teal-50 rounded hover:bg-teal-100 text-center">
                <div class="font-medium text-teal-800">Stock In</div>
                <div class="text-xs text-teal-600 mt-1">Record deliveries</div>
            </a>
        </div>

        <!-- Branch Count -->
        <div class="mt-6 pt-4 border-t">
            <div class="flex justify-between text-sm text-gray-600">
                <span>Total Branches</span>
                <span class="font-bold">{{ $branches->count() }}</span>
            </div>
            <div class="flex justify-between text-sm text-gray-600 mt-1">
                <span>Total Users</span>
                <span class="font-bold">{{ $totalUsers }}</span>
            </div>
        </div>
    </div>
</div>
@endsection
