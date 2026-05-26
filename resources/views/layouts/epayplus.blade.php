<!DOCTYPE html>
<html>
<head>
    <title>ePay Plus Admin — @yield('title', 'Dashboard')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .sidebar { width: 250px; min-height: 100vh; transition: all .3s; }
        .sidebar .nav-link { color: rgba(255,255,255,.7); padding: .6rem 1rem; font-size: .875rem; border-radius: .375rem; margin: 1px 8px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,.1); }
        .sidebar .nav-link i { width: 20px; text-align: center; margin-right: 8px; }
        .sidebar .nav-section { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: rgba(255,255,255,.4); padding: .75rem 1rem .25rem; margin-top: .5rem; }
        .content-wrapper { flex: 1; min-width: 0; }
        @media (max-width: 991px) {
            .sidebar { position: fixed; left: -250px; z-index: 1050; }
            .sidebar.show { left: 0; }
            .sidebar-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1040; }
            .sidebar.show + .sidebar-backdrop { display: block; }
        }
    </style>
    @stack('styles')
</head>
<body class="bg-light">
    <div class="d-flex">
        {{-- Sidebar --}}
        <nav class="sidebar bg-dark d-flex flex-column" id="sidebar">
            <div class="p-3 border-bottom border-secondary">
                <a class="text-decoration-none d-flex align-items-center" href="{{ route('epayplus.dashboard') }}">
                    <i class="bi bi-phone text-success fs-4 me-2"></i>
                    <span class="fw-bold text-white fs-5">ePay Plus</span>
                </a>
            </div>

            <div class="flex-grow-1 overflow-auto py-2">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('epayplus.dashboard') ? 'active' : '' }}" href="{{ route('epayplus.dashboard') }}">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>

                    <li><div class="nav-section">Management</div></li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('epayplus.retailers*') ? 'active' : '' }}" href="{{ route('epayplus.retailers') }}">
                            <i class="bi bi-people"></i> Retailers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('epayplus.providers*') ? 'active' : '' }}" href="{{ route('epayplus.providers') }}">
                            <i class="bi bi-building"></i> Providers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('epayplus.products*') ? 'active' : '' }}" href="{{ route('epayplus.products') }}">
                            <i class="bi bi-box-seam"></i> Products
                        </a>
                    </li>

                    <li><div class="nav-section">Operations</div></li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('epayplus.transactions*') ? 'active' : '' }}" href="{{ route('epayplus.transactions') }}">
                            <i class="bi bi-list-check"></i> Transactions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('epayplus.topups*') ? 'active' : '' }}" href="{{ route('epayplus.topups') }}">
                            <i class="bi bi-wallet2"></i> Top-ups
                            @php $pendingCount = \App\Models\EPayPlus\Topup::where('status','PENDING')->count(); @endphp
                            @if($pendingCount > 0)
                                <span class="badge bg-warning text-dark ms-1">{{ $pendingCount }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('epayplus.announcements*') ? 'active' : '' }}" href="{{ route('epayplus.announcements') }}">
                            <i class="bi bi-megaphone"></i> Announcements
                        </a>
                    </li>

                    <li><div class="nav-section">Analytics</div></li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('epayplus.reports*') ? 'active' : '' }}" href="{{ route('epayplus.reports') }}">
                            <i class="bi bi-bar-chart-line"></i> Reports
                        </a>
                    </li>

                    <li><div class="nav-section">System</div></li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('epayplus.settings*') ? 'active' : '' }}" href="{{ route('epayplus.settings') }}">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('epayplus.audit-log*') ? 'active' : '' }}" href="{{ route('epayplus.audit-log') }}">
                            <i class="bi bi-journal-text"></i> Audit Log
                        </a>
                    </li>
                </ul>
            </div>

            @auth
            <div class="border-top border-secondary p-3">
                <div class="d-flex align-items-center text-white small">
                    <i class="bi bi-person-circle me-2"></i>
                    <div class="flex-grow-1">
                        <div class="fw-medium">{{ auth()->user()->name }}</div>
                        <div class="text-muted small">{{ auth()->user()->role }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary border-0" title="Logout">
                            <i class="bi bi-box-arrow-right"></i>
                        </button>
                    </form>
                </div>
            </div>
            @endauth
        </nav>
        <div class="sidebar-backdrop" onclick="document.getElementById('sidebar').classList.remove('show')"></div>

        {{-- Main Content --}}
        <div class="content-wrapper">
            <nav class="navbar navbar-light bg-white border-bottom shadow-sm d-lg-none">
                <div class="container-fluid">
                    <button class="btn btn-outline-dark" onclick="document.getElementById('sidebar').classList.toggle('show')">
                        <i class="bi bi-list"></i>
                    </button>
                    <span class="navbar-brand mb-0 fw-bold text-success">ePay Plus</span>
                    <div></div>
                </div>
            </nav>

            @if(session('success'))
            <div class="container-fluid px-4 pt-3">
                <div class="alert alert-success alert-dismissible fade show mb-0" role="alert">
                    <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
            @endif

            @if(session('error'))
            <div class="container-fluid px-4 pt-3">
                <div class="alert alert-danger alert-dismissible fade show mb-0" role="alert">
                    <i class="bi bi-exclamation-circle me-1"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
            @endif

            @if($errors->any())
            <div class="container-fluid px-4 pt-3">
                <div class="alert alert-danger alert-dismissible fade show mb-0">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
            @endif

            <main class="p-4">
                @yield('content')
            </main>

            <footer class="text-center text-muted py-3 border-top">
                <small>&copy; {{ date('Y') }} ePay Plus. All rights reserved.</small>
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelector('meta[name="csrf-token"]') &&
        (window._csrf = document.querySelector('meta[name="csrf-token"]').content);
    </script>
    @stack('scripts')
</body>
</html>
