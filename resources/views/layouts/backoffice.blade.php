@php $isEpayPlus = is_epayplus_product(); $brandName = $isEpayPlus ? 'ePay Plus' : 'INSA POS'; @endphp
<!DOCTYPE html>
<html>
<head>
    <title>{{ $brandName }} — Back Office</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="flex min-h-screen">

        <!-- SIDEBAR -->
        <aside class="w-56 bg-gray-900 text-gray-300 flex flex-col flex-shrink-0">
            <div class="p-4 border-b border-gray-800">
                <a href="{{ route('backoffice.dashboard') }}" class="text-white font-bold text-lg">{{ $brandName }}</a>
                <div class="text-xs text-gray-500 mt-1">Back Office</div>
            </div>

            <nav class="flex-1 py-4 text-sm space-y-1">
                @if(auth()->user()->isSuperAdmin())
                <div class="px-3 pb-3">
                    <a href="{{ route('super-admin.dashboard') }}" class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-500 transition shadow-sm {{ request()->routeIs('super-admin.*') ? 'ring-2 ring-indigo-300 ring-offset-2 ring-offset-gray-900' : '' }}">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        Super Admin Panel
                    </a>
                </div>
                @endif
                <a href="{{ route('backoffice.dashboard') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('backoffice.dashboard') ? 'bg-gray-800 text-white' : '' }}">Dashboard</a>
                <div class="px-4 pt-3 pb-1 text-xs uppercase text-gray-600 tracking-wider">Reports</div>
                <a href="{{ route('backoffice.reports.daily-sales') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('backoffice.reports.daily-sales') ? 'bg-gray-800 text-white' : '' }}">Daily Sales</a>
                <a href="{{ route('backoffice.reports.product-performance') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('backoffice.reports.product-performance') ? 'bg-gray-800 text-white' : '' }}">Product Performance</a>

                <a href="{{ route('backoffice.analytics') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('backoffice.analytics*') ? 'bg-gray-800 text-white' : '' }}">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        Analytics
                    </span>
                </a>

                <div class="px-4 pt-3 pb-1 text-xs uppercase text-gray-600 tracking-wider">Catalog</div>
                <a href="{{ route('admin.products.index') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.products.*') ? 'bg-gray-800 text-white' : '' }}">Products</a>
                <a href="{{ route('admin.categories.index') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.categories.*') ? 'bg-gray-800 text-white' : '' }}">Categories</a>

                <div class="px-4 pt-3 pb-1 text-xs uppercase text-gray-600 tracking-wider">Inventory</div>
                <a href="{{ route('admin.inventory.dashboard') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.inventory.*') ? 'bg-gray-800 text-white' : '' }}">Inventory Dashboard</a>
                <a href="{{ route('backoffice.inventory.batches') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('backoffice.inventory.batches*') ? 'bg-gray-800 text-white' : '' }}">Batches</a>
                <a href="{{ route('backoffice.inventory.movements') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('backoffice.inventory.movements') ? 'bg-gray-800 text-white' : '' }}">Movements</a>
                <a href="{{ route('backoffice.inventory.adjustment') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('backoffice.inventory.adjustment*') ? 'bg-gray-800 text-white' : '' }}">Adjustments</a>
                <a href="{{ route('backoffice.inventory.expiry') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('backoffice.inventory.expiry*') ? 'bg-gray-800 text-white' : '' }}">Expiry Alerts</a>
                <a href="{{ route('backoffice.inventory.forecast') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('backoffice.inventory.forecast') ? 'bg-gray-800 text-white' : '' }}">Reorder Forecast</a>
                <a href="{{ route('stockman.stock-in') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('stockman.stock-in*') ? 'bg-gray-800 text-white' : '' }}">Stock In</a>

                <div class="px-4 pt-3 pb-1 text-xs uppercase text-gray-600 tracking-wider">Operations</div>
                <a href="{{ route('backoffice.shifts.dashboard') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('backoffice.shifts.dashboard') ? 'bg-gray-800 text-white' : '' }}">Shift Dashboard</a>
                <a href="{{ route('backoffice.shifts.variance') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('backoffice.shifts.variance') ? 'bg-gray-800 text-white' : '' }}">Variance Report</a>
                <a href="{{ route('backoffice.shifts.audit') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('backoffice.shifts.audit') ? 'bg-gray-800 text-white' : '' }}">Audit Trail</a>

                <div class="px-4 pt-3 pb-1 text-xs uppercase text-gray-600 tracking-wider">Readings</div>
                <a href="{{ route('readings.x') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('readings.x') ? 'bg-gray-800 text-white' : '' }}">X-Reading</a>
                <a href="{{ route('readings.z') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('readings.z') ? 'bg-gray-800 text-white' : '' }}">Z-Reading</a>

                @if(auth()->user()->canManageUsers())
                <div class="px-4 pt-3 pb-1 text-xs uppercase text-gray-600 tracking-wider">Administration</div>
                <a href="{{ route('admin.users.index') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.users.*') ? 'bg-gray-800 text-white' : '' }}">Users</a>
                <a href="{{ route('admin.branches.index') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('admin.branches.*') ? 'bg-gray-800 text-white' : '' }}">Branches</a>
                @endif

                @if(auth()->user()->canManageSettings())
                <a href="{{ route('pos.settings') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('pos.settings*') ? 'bg-gray-800 text-white' : '' }}">Settings</a>
                @elseif(auth()->user()->isManager())
                <a href="{{ route('pos.settings') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white {{ request()->routeIs('pos.settings*') ? 'bg-gray-800 text-white' : '' }}">Settings (View)</a>
                @endif

                <div class="px-4 pt-3 pb-1 text-xs uppercase text-gray-600 tracking-wider">POS</div>
                <a href="{{ route('pos.cashier') }}" class="block px-4 py-2 hover:bg-gray-800 hover:text-white text-green-400">Open Cashier</a>
            </nav>

            <!-- User info at bottom -->
            <div class="p-4 border-t border-gray-800">
                <div class="text-sm text-white font-medium">{{ auth()->user()->name }}</div>
                <div class="text-xs text-gray-500">{{ ucfirst(auth()->user()->role) }} &middot; {{ auth()->user()->branch?->name ?? 'No Branch' }}</div>
                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="text-xs text-red-400 hover:text-red-300">Logout</button>
                </form>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <div class="flex-1 flex flex-col">
            <header class="bg-white shadow px-6 py-3 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">@yield('page-title', 'Back Office')</h2>
                <div class="flex items-center gap-4 text-sm">
                    @if(auth()->user()->isSuperAdmin())
                    <a href="{{ route('super-admin.dashboard') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold text-sm hover:bg-indigo-700 shadow-sm transition">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        Super Admin Panel
                    </a>
                    @endif
                    @php
                        $roleBadge = match(auth()->user()->role) {
                            'super_admin' => 'bg-indigo-100 text-indigo-800',
                            'owner'       => 'bg-red-100 text-red-800',
                            'admin'       => 'bg-purple-100 text-purple-800',
                            'manager'     => 'bg-blue-100 text-blue-800',
                            default       => 'bg-gray-100 text-gray-800',
                        };
                        $roleLabel = auth()->user()->role === 'super_admin' ? 'Super Admin' : ucfirst(str_replace('_', ' ', auth()->user()->role));
                    @endphp
                    <span class="px-2 py-1 rounded text-xs font-medium {{ $roleBadge }}">{{ $roleLabel }}</span>
                    <span class="text-gray-500">{{ auth()->user()->name }}</span>
                    <span class="text-gray-400">&middot;</span>
                    <span class="text-gray-500">{{ auth()->user()->branch?->name ?? 'No Branch' }}</span>
                    <span class="text-gray-400">&middot;</span>
                    <span class="text-gray-400">{{ now()->format('M d, Y — h:i A') }}</span>
                </div>
            </header>

            @if(session('success'))
            <div class="px-6 pt-4">
                <div class="bg-green-100 text-green-800 p-3 rounded text-sm">{{ session('success') }}</div>
            </div>
            @endif

            @if(session('error'))
            <div class="px-6 pt-4">
                <div class="bg-red-100 text-red-800 p-3 rounded text-sm">{{ session('error') }}</div>
            </div>
            @endif

            <main class="flex-1 p-6">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
