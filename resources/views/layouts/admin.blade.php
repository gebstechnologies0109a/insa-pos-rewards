@php $isEpayPlus = is_epayplus_product(); $brandName = $isEpayPlus ? 'ePay Plus' : 'INSA POS'; @endphp
<!DOCTYPE html>
<html>
<head>
    <title>{{ $brandName }} Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <nav class="bg-gray-900 text-white">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <a href="{{ route('admin.products.index') }}" class="font-bold text-lg">{{ $brandName }}</a>
                <div class="flex gap-4 text-sm">
                    <a href="{{ route('admin.products.index') }}" class="hover:text-blue-300 {{ request()->routeIs('admin.products.*') ? 'text-blue-400' : '' }}">Products</a>
                    <a href="{{ route('admin.categories.index') }}" class="hover:text-blue-300 {{ request()->routeIs('admin.categories.*') ? 'text-blue-400' : '' }}">Categories</a>
                    <a href="{{ route('admin.branches.index') }}" class="hover:text-blue-300 {{ request()->routeIs('admin.branches.*') ? 'text-blue-400' : '' }}">Branches</a>
                    @if(auth()->user()->hasPermission('license.sessions.view'))
                    <a href="{{ route('admin.pos-sessions.index') }}" class="hover:text-blue-300 {{ request()->routeIs('admin.pos-sessions.*') ? 'text-blue-400' : '' }}">POS Sessions</a>
                    @endif
                    <a href="{{ route('admin.inventory.dashboard') }}" class="hover:text-blue-300 {{ request()->routeIs('admin.inventory.*') ? 'text-blue-400' : '' }}">Inventory</a>
                    <a href="{{ route('pos.settings') }}" class="hover:text-blue-300 {{ request()->routeIs('pos.settings') ? 'text-blue-400' : '' }}">Settings</a>
                    <span class="text-gray-600">|</span>
                    <a href="{{ route('pos.cashier') }}" class="hover:text-green-300 text-green-400">Open Cashier</a>
                </div>
            </div>
            @auth
            <div class="flex items-center gap-4 text-sm">
                <span>{{ auth()->user()->name }} ({{ auth()->user()->role }})</span>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="hover:text-red-300">Logout</button>
                </form>
            </div>
            @endauth
        </div>
    </nav>

    @if(session('success'))
    <div class="max-w-7xl mx-auto px-4 mt-4">
        <div class="bg-green-100 text-green-800 p-3 rounded text-sm">{{ session('success') }}</div>
    </div>
    @endif

    @if(session('error'))
    <div class="max-w-7xl mx-auto px-4 mt-4">
        <div class="bg-red-100 text-red-800 p-3 rounded text-sm">{{ session('error') }}</div>
    </div>
    @endif

    <main class="max-w-7xl mx-auto px-4 py-6">
        @yield('content')
    </main>
</body>
</html>
