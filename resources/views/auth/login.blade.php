@php
    $isEpayPlus = is_epayplus_product();
    $brandName = $isEpayPlus ? 'ePay Plus' : 'INSA POS';
    $tagline = $isEpayPlus ? 'All-in-one payment platform' : 'Point of sale for retail';
    $accentHeader = $isEpayPlus ? 'bg-emerald-800' : 'bg-slate-800';
    $accentBtn = $isEpayPlus ? 'bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500' : 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500';
    $accentRing = $isEpayPlus ? 'focus:ring-emerald-500' : 'focus:ring-blue-500';
    $accentLogo = $isEpayPlus ? 'bg-emerald-600' : 'bg-blue-600';
    $hasViteAssets = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $brandName }} — Sign in</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if ($hasViteAssets)
        @fonts
        @vite(['resources/css/auth-login.css'])
    @else
        <style>
            *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
            html{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;-webkit-text-size-adjust:100%}
            body{min-height:100vh;min-height:100dvh;background:#f1f5f9;color:#0f172a;display:flex;flex-direction:column}
            .login-header{background:#1e293b;color:#fff;padding:1.25rem 1.5rem;text-align:center}
            .login-header.epay{background:#065f46}
            .login-header h1{font-size:1.375rem;font-weight:700;letter-spacing:-.02em}
            .login-header p{font-size:.875rem;color:#cbd5e1;margin-top:.25rem}
            .login-main{flex:1;display:flex;align-items:center;justify-content:center;padding:1.5rem}
            .login-card{width:100%;max-width:28rem;background:#fff;border-radius:1rem;border:1px solid #e2e8f0;box-shadow:0 10px 40px -12px rgba(15,23,42,.15);padding:1.75rem 1.5rem 1.5rem}
            @media(min-width:640px){.login-card{padding:2rem 2rem 1.75rem}}
            .login-logo{width:3.5rem;height:3.5rem;border-radius:1rem;background:#2563eb;color:#fff;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-weight:700;font-size:1.125rem}
            .login-logo.epay{background:#059669}
            .login-card h2{font-size:1.125rem;font-weight:600;text-align:center;margin-bottom:.25rem}
            .login-card .sub{text-align:center;font-size:.875rem;color:#64748b;margin-bottom:1.5rem}
            .alert{display:flex;gap:.75rem;padding:.875rem 1rem;border-radius:.75rem;font-size:.875rem;line-height:1.45;margin-bottom:1.25rem}
            .alert-warn{background:#fffbeb;border:1px solid #fde68a;color:#92400e}
            .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
            .field{margin-bottom:1.125rem}
            .field label{display:block;font-size:.875rem;font-weight:500;color:#334155;margin-bottom:.375rem}
            .field input{width:100%;min-height:3rem;padding:.75rem 1rem;font-size:1rem;border:1px solid #cbd5e1;border-radius:.75rem;background:#fff}
            .field input:focus{outline:2px solid #2563eb;outline-offset:0;border-color:#2563eb}
            .remember{display:flex;align-items:center;gap:.625rem;margin-bottom:1.25rem;min-height:2.75rem}
            .remember input{width:1.25rem;height:1.25rem}
            .remember label{font-size:.9375rem;color:#475569}
            .btn-submit{width:100%;min-height:3.25rem;padding:.875rem 1rem;font-size:1.0625rem;font-weight:600;color:#fff;background:#2563eb;border:none;border-radius:.75rem;cursor:pointer}
            .btn-submit.epay{background:#059669}
            .login-footer{text-align:center;font-size:.75rem;color:#94a3b8;padding:1rem 1.5rem}
        </style>
    @endif
</head>
<body class="min-h-screen min-h-[100dvh] bg-slate-100 text-slate-900 flex flex-col antialiased"
      style="padding-bottom: env(safe-area-inset-bottom, 0); padding-top: env(safe-area-inset-top, 0);">

    <header class="{{ $accentHeader }} text-white px-4 py-5 sm:py-6 text-center shadow-md @unless($hasViteAssets) login-header @if($isEpayPlus) epay @endif @endunless">
        <div class="max-w-lg mx-auto flex flex-col items-center gap-3">
            <div class="w-14 h-14 rounded-2xl {{ $accentLogo }} text-white flex items-center justify-center shadow-lg ring-2 ring-white/20 @unless($hasViteAssets) login-logo @if($isEpayPlus) epay @endif @endunless"
                 aria-hidden="true">
                @if($isEpayPlus)
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                @else
                    <span class="text-lg font-bold tracking-tight">IN</span>
                @endif
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight">{{ $brandName }}</h1>
                <p class="text-sm text-slate-300 mt-0.5">{{ $tagline }}</p>
            </div>
        </div>
    </header>

    <main class="flex-1 flex items-center justify-center px-4 py-6 sm:py-10 @unless($hasViteAssets) login-main @endunless">
        <div class="w-full max-w-md">
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200/80 overflow-hidden @unless($hasViteAssets) login-card @endunless">
                <div class="px-6 pt-7 pb-2 sm:px-8 sm:pt-8">
                    <h2 class="text-lg font-semibold text-slate-900 text-center">Sign in</h2>
                    <p class="text-sm text-slate-500 text-center mt-1 mb-6">Use your staff account to continue</p>

                    @if(!empty($loginError))
                    <div class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3.5 text-sm text-amber-950 mb-5 @unless($hasViteAssets) alert alert-warn @endunless"
                         role="alert">
                        <svg class="w-5 h-5 shrink-0 text-amber-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <p class="leading-relaxed">{{ $loginError }}</p>
                    </div>
                    @elseif(!empty($isAndroidApp))
                    <div class="flex gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 mb-5" role="status">
                        <svg class="w-5 h-5 shrink-0 text-slate-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        <p class="leading-relaxed">Sign in with the same account you use for the POS cashier.</p>
                    </div>
                    @endif

                    @if($errors->any())
                    <div class="flex gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3.5 text-sm text-red-900 mb-5 @unless($hasViteAssets) alert alert-error @endunless"
                         role="alert">
                        <svg class="w-5 h-5 shrink-0 text-red-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="leading-relaxed">{{ $errors->first() }}</p>
                    </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="space-y-5" novalidate>
                        @csrf

                        <div @class(['field' => ! $hasViteAssets])>
                            <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Email address</label>
                            <input type="email"
                                   id="email"
                                   name="email"
                                   value="{{ old('email') }}"
                                   required
                                   autofocus
                                   autocomplete="username"
                                   inputmode="email"
                                   class="w-full min-h-[3rem] px-4 py-3 text-base text-slate-900 bg-white border border-slate-300 rounded-xl shadow-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 {{ $accentRing }} focus:border-transparent transition"
                                   placeholder="you@store.com">
                        </div>

                        <div @class(['field' => ! $hasViteAssets])>
                            <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
                            <input type="password"
                                   id="password"
                                   name="password"
                                   required
                                   autocomplete="current-password"
                                   class="w-full min-h-[3rem] px-4 py-3 text-base text-slate-900 bg-white border border-slate-300 rounded-xl shadow-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 {{ $accentRing }} focus:border-transparent transition"
                                   placeholder="••••••••">
                        </div>

                        <div @class(['flex items-center gap-3 min-h-[2.75rem]', 'remember' => ! $hasViteAssets])>
                            <input type="checkbox"
                                   id="remember"
                                   name="remember"
                                   class="w-5 h-5 rounded border-slate-300 text-blue-600 focus:ring-2 {{ $accentRing }}">
                            <label for="remember" class="text-base text-slate-600 select-none">Remember me on this device</label>
                        </div>

                        <button type="submit"
                                @class([
                                    'w-full min-h-[3.25rem] px-4 py-3.5 text-base font-semibold text-white rounded-xl shadow-md transition focus:outline-none focus:ring-2 focus:ring-offset-2',
                                    $accentBtn,
                                    'btn-submit' => ! $hasViteAssets,
                                    'epay' => ! $hasViteAssets && $isEpayPlus,
                                ])>
                            Sign in
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <footer @class(['text-center text-xs text-slate-400 px-4 py-4', 'login-footer' => ! $hasViteAssets])>
        &copy; {{ date('Y') }} {{ $brandName }}
    </footer>
</body>
</html>
