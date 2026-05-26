<!DOCTYPE html>
<html>
<head>
    <title>ePay Plus Admin — @yield('title', 'Dashboard')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @stack('styles')
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-success" href="{{ route('epayplus.dashboard') }}">
                <i class="bi bi-phone"></i> ePay Plus
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#epNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="epNavbar">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('epayplus.dashboard') ? 'active' : '' }}" href="{{ route('epayplus.dashboard') }}">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('epayplus.retailers*') ? 'active' : '' }}" href="{{ route('epayplus.retailers') }}">
                            <i class="bi bi-people"></i> Retailers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('epayplus.transactions') ? 'active' : '' }}" href="{{ route('epayplus.transactions') }}">
                            <i class="bi bi-list-check"></i> Transactions
                        </a>
                    </li>
                </ul>
                @auth
                <div class="navbar-text text-light">
                    <span class="me-3">{{ auth()->user()->name }} <span class="badge bg-success">{{ auth()->user()->role }}</span></span>
                    <form method="POST" action="{{ route('logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-light">Logout</button>
                    </form>
                </div>
                @endauth
            </div>
        </div>
    </nav>

    @if(session('success'))
    <div class="container-fluid mt-3">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="container-fluid mt-3">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    @endif

    <main>
        @yield('content')
    </main>

    <footer class="text-center text-muted py-3 mt-4 border-top">
        <small>&copy; {{ date('Y') }} ePay Plus. All rights reserved.</small>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
