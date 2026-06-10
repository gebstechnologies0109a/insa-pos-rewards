@props([
    'branches' => null,
    'totalUsers' => null,
])

@php
    $user = auth()->user();
    $branchCount = $branches?->count();
@endphp

<div class="bg-white rounded-lg shadow p-6">
    <h3 class="font-semibold text-lg mb-4">Quick Links</h3>
    <div class="grid grid-cols-2 gap-3">
        @if($user->canAccessSuperAdminPanel())
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
        @if($user->canManageUsers())
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
        <a href="{{ route('backoffice.shifts.dashboard') }}" class="p-4 bg-slate-50 rounded hover:bg-slate-100 text-center">
            <div class="font-medium text-slate-800">Shifts</div>
            <div class="text-xs text-slate-600 mt-1">Shift dashboard</div>
        </a>
        <a href="{{ route('backoffice.reports.daily-sales') }}" class="p-4 bg-orange-50 rounded hover:bg-orange-100 text-center">
            <div class="font-medium text-orange-800">Daily Sales</div>
            <div class="text-xs text-orange-600 mt-1">Sales report</div>
        </a>
    </div>

    @if($branchCount !== null || $totalUsers !== null)
    <div class="mt-6 pt-4 border-t">
        @if($branchCount !== null)
        <div class="flex justify-between text-sm text-gray-600">
            <span>Total Branches</span>
            <span class="font-bold">{{ $branchCount }}</span>
        </div>
        @endif
        @if($totalUsers !== null)
        <div class="flex justify-between text-sm text-gray-600 mt-1">
            <span>Total Users</span>
            <span class="font-bold">{{ $totalUsers }}</span>
        </div>
        @endif
    </div>
    @endif
</div>
