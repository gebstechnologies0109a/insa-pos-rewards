@php $brandName = is_epayplus_product() ? 'ePay Plus' : 'INSA POS'; @endphp
<!DOCTYPE html>
<html>
<head>
    <title>{{ $brandName }} — Owner</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100">
    <div class="flex min-h-screen">
        <aside class="w-56 bg-slate-900 text-slate-300 flex flex-col flex-shrink-0">
            <div class="p-4 border-b border-slate-800">
                <a href="{{ route('owner.dashboard') }}" class="text-white font-bold text-lg">{{ $brandName }}</a>
                <div class="text-xs text-slate-500 mt-1">Owner Console</div>
            </div>
            <nav class="flex-1 py-4 text-sm space-y-1">
                <a href="{{ route('owner.dashboard') }}" class="block px-4 py-2 hover:bg-slate-800 hover:text-white {{ request()->routeIs('owner.dashboard') ? 'bg-slate-800 text-white' : '' }}">Dashboard</a>
                <a href="{{ route('backoffice.dashboard') }}" class="block px-4 py-2 hover:bg-slate-800 hover:text-white">Back Office</a>
                <a href="{{ route('backoffice.reports.daily-sales') }}" class="block px-4 py-2 hover:bg-slate-800 hover:text-white">Daily Sales</a>
                <a href="{{ route('backoffice.reports.product-performance') }}" class="block px-4 py-2 hover:bg-slate-800 hover:text-white">Product Performance</a>
                <a href="{{ route('pos.cashier') }}" class="block px-4 py-2 text-green-400 hover:bg-slate-800">Open Cashier</a>
            </nav>
            <div class="p-4 border-t border-slate-800">
                <div class="text-sm text-white font-medium">{{ auth()->user()->name }}</div>
                <div class="text-xs text-slate-500">{{ ucfirst(auth()->user()->role) }}</div>
                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="text-xs text-red-400 hover:text-red-300">Logout</button>
                </form>
            </div>
        </aside>
        <main class="flex-1 p-6 overflow-auto">
            @if(session('success'))
            <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
