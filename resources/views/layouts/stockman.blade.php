@php $isEpayPlus = is_epayplus_product(); $brandName = $isEpayPlus ? 'ePay Plus' : 'INSA POS'; @endphp
<!DOCTYPE html>
<html>
<head>
    <title>{{ $brandName }} — Stockman</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <nav class="bg-gray-800 text-white">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <span class="font-bold text-lg">{{ $brandName }} — Stockman</span>
                <div class="flex gap-4 text-sm">
                    <a href="{{ route('stockman.inventory') }}" class="hover:text-blue-300 {{ request()->routeIs('stockman.inventory') ? 'text-blue-400' : '' }}">Inventory</a>
                    <a href="{{ route('stockman.audit') }}" class="hover:text-blue-300 {{ request()->routeIs('stockman.audit*') ? 'text-blue-400' : '' }}">Stock Audit</a>
                    <a href="{{ route('stockman.stock-in') }}" class="hover:text-blue-300 {{ request()->routeIs('stockman.stock-in*') ? 'text-blue-400' : '' }}">Stock In</a>
                </div>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <span>{{ auth()->user()->name }} &middot; {{ auth()->user()->branch?->name ?? 'No Branch' }}</span>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="hover:text-red-300">Logout</button>
                </form>
            </div>
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
