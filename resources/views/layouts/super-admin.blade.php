<!DOCTYPE html>
<html>
<head>
    <title>INSA POS — Super Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">

        <!-- SIDEBAR -->
        <aside class="w-60 bg-indigo-950 text-indigo-200 flex flex-col flex-shrink-0">
            <div class="p-5 border-b border-indigo-800">
                <a href="{{ route('super-admin.dashboard') }}" class="text-white font-bold text-lg">INSA POS</a>
                <div class="text-xs text-indigo-400 mt-1 font-medium">Super Admin Panel</div>
            </div>

            <nav class="flex-1 py-4 text-sm space-y-1">
                <a href="{{ route('super-admin.dashboard') }}" class="block px-5 py-2.5 hover:bg-indigo-900 hover:text-white transition {{ request()->routeIs('super-admin.dashboard') ? 'bg-indigo-900 text-white' : '' }}">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Dashboard
                    </span>
                </a>

                <div class="px-5 pt-4 pb-1 text-xs uppercase text-indigo-500 tracking-wider font-semibold">Management</div>

                <a href="{{ route('super-admin.licenses.index') }}" class="block px-5 py-2.5 hover:bg-indigo-900 hover:text-white transition {{ request()->routeIs('super-admin.licenses.*') ? 'bg-indigo-900 text-white' : '' }}">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        Licenses
                    </span>
                </a>

                <a href="{{ route('super-admin.branches.index') }}" class="block px-5 py-2.5 hover:bg-indigo-900 hover:text-white transition {{ request()->routeIs('super-admin.branches.*') ? 'bg-indigo-900 text-white' : '' }}">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        Branches
                    </span>
                </a>

                <div class="px-5 pt-4 pb-1 text-xs uppercase text-indigo-500 tracking-wider font-semibold">Quick Links</div>

                <a href="{{ route('backoffice.dashboard') }}" class="block px-5 py-2.5 hover:bg-indigo-900 hover:text-white transition">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                        Back Office
                    </span>
                </a>
            </nav>

            <!-- User info -->
            <div class="p-5 border-t border-indigo-800">
                <div class="text-sm text-white font-medium">{{ auth()->user()->name }}</div>
                <div class="text-xs text-indigo-400">Super Admin</div>
                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="text-xs text-red-400 hover:text-red-300 transition">Logout</button>
                </form>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <div class="flex-1 flex flex-col">
            <header class="bg-white shadow-sm px-6 py-4 flex items-center justify-between border-b">
                <h2 class="text-lg font-semibold text-gray-800">@yield('page-title', 'Super Admin')</h2>
                <div class="flex items-center gap-4 text-sm">
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">Super Admin</span>
                    <span class="text-gray-500">{{ auth()->user()->name }}</span>
                    <span class="text-gray-400">{{ now()->format('M d, Y — h:i A') }}</span>
                </div>
            </header>

            @if(session('success'))
            <div class="px-6 pt-4">
                <div class="bg-green-50 border border-green-200 text-green-800 p-3 rounded-lg text-sm">{{ session('success') }}</div>
            </div>
            @endif

            @if(session('error'))
            <div class="px-6 pt-4">
                <div class="bg-red-50 border border-red-200 text-red-800 p-3 rounded-lg text-sm">{{ session('error') }}</div>
            </div>
            @endif

            <main class="flex-1 p-6">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
