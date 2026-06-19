@php
    $isEpayPlus = is_epayplus_product();
    $brandName = $isEpayPlus ? 'ePay Plus' : 'INSA POS';
    $canEditCdSettings = in_array(auth()->user()?->role, ['cashier', 'manager', 'admin', 'owner', 'super_admin'], true);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=0.8, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $brandName }} — Cashier</title>
    <script>
        window.INSA_IS_SUPER_ADMIN = @json(auth()->check() && auth()->user()->isSuperAdmin());
    </script>
    @if (vite_manifest_has(['resources/css/pos-cashier.css']))
        @vite(['resources/css/pos-cashier.css'])
    @else
        <script src="https://cdn.tailwindcss.com" defer></script>
        <script defer>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof tailwind !== 'undefined') {
                    tailwind.config = { theme: { extend: { screens: { 'xs': '640px', '3xl': '1920px' } } } };
                }
            });
        </script>
    @endif
    <style>
        [x-cloak] { display: none !important; }
        /* Visible shell if Alpine/CDN is slow or blocked (WebView tablets) */
        body.insapos-alpine-pending { background: #f3f4f6; }
        body.insapos-alpine-pending::before {
            content: 'Loading cashier…';
            display: block;
            text-align: center;
            padding: 2rem 1rem;
            color: #475569;
            font-family: system-ui, sans-serif;
            font-size: 1rem;
        }
        .product-tile:active { transform: scale(0.95); }
        .product-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.375rem; }
        @media (min-width: 640px) { .product-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (min-width: 1024px) { .product-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.5rem; } }
        @media (min-width: 1280px) { .product-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); } }
        @media (min-width: 1920px) { .product-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
        .product-card { min-height: 4.5rem; }
        .badge-expiry { font-size: 9px; padding: 0 4px; border-radius: 4px; background: #fef3c7; color: #92400e; }
        .badge-stock-low { background: #fef3c7; color: #92400e; }
        .badge-stock-out { background: #fee2e2; color: #991b1b; }
        .badge-stock-ok { background: #d1fae5; color: #065f46; }
        #offline-indicator.offline { background: #fef2f2; color: #991b1b; }
        #offline-indicator.online { background: #ecfdf5; color: #065f46; }
        #posScreen, #checkoutScreen { min-height: 0; }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        @media (min-width: 1280px) { .status-dot { width: 10px; height: 10px; } }
        @keyframes pulse-green { 0%,100%{ box-shadow:0 0 0 0 rgba(16,185,129,.4) } 50%{ box-shadow:0 0 0 6px rgba(16,185,129,0) } }
        @keyframes pulse-yellow { 0%,100%{ box-shadow:0 0 0 0 rgba(245,158,11,.4) } 50%{ box-shadow:0 0 0 6px rgba(245,158,11,0) } }
        .status-dot.online { background: #10b981; animation: pulse-green 2s infinite; }
        .status-dot.syncing { background: #f59e0b; animation: pulse-yellow 1s infinite; }
        .status-dot.offline { background: #ef4444; }
        .buddy-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
        @media (min-width: 1280px) { .buddy-dot { width: 8px; height: 8px; } }
        .buddy-dot.connected { background: #10b981; animation: pulse-green 2s infinite; }
        .buddy-dot.disconnected { background: #ef4444; }
        .toast-enter { animation: toastIn .3s ease-out; }
        @keyframes toastIn { from { transform: translateY(-20px); opacity:0 } to { transform:translateY(0); opacity:1 } }
        @keyframes toastOut { from { opacity:1 } to { opacity:0; transform:translateY(-20px) } }
        .modal-overlay { backdrop-filter: blur(2px); }
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }

        /* Responsive base font scaling */
        html { font-size: 13px; }
        @media (min-width: 1024px) { html { font-size: 14px; } }
        @media (min-width: 1280px) { html { font-size: 15px; } }
        @media (min-width: 1920px) { html { font-size: 16px; } }

        /* Safe area for Android system bars and notch devices */
        body { padding-bottom: env(safe-area-inset-bottom, 0px); }

        /* Fixed bottom action bars — stay above Android system navigation */
        .pos-fixed-bottom {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 50;
            padding-bottom: env(safe-area-inset-bottom, 0px);
            background: #fff;
            border-top: 1px solid #e5e7eb;
            box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.08);
        }
        .pos-content-pad-bottom {
            padding-bottom: calc(4.75rem + env(safe-area-inset-bottom, 0px));
        }
        .pos-header-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            min-width: 44px;
            min-height: 44px;
            flex-shrink: 0;
        }

        /* Prevent modal overflow on small screens */
        .modal-overlay > div { max-height: calc(100vh - 24px); max-height: calc(100dvh - 24px); overflow-y: auto; }

        /* Retail mode scan result animation */
        .animate-in { animation: slideUp .2s ease-out; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
    </style>
    <script src="https://unpkg.com/dexie@4/dist/dexie.min.js"></script>
    <script src="{{ asset('js/db.js') }}"></script>
    <script src="{{ asset('js/terminal-session.js') }}"></script>
    <script src="{{ asset('js/insabuddy.js') }}?v=3.0.45"></script>
    <script src="{{ asset('js/sync-engine.js') }}?v=3.0.45"></script>
</head>
<body class="bg-gray-100 flex flex-col overflow-hidden insapos-alpine-pending" style="height:100vh;height:100dvh" x-data="posApp()" x-init="init()" x-cloak
      @keydown.window="handleBarcodeKey($event)">

<!-- LICENSE / SEAT LIMIT -->
<div x-show="licenseBlocked" class="fixed inset-0 bg-slate-900/95 z-[250] flex items-center justify-center p-6" x-cloak>
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 text-center">
        <div class="text-red-600 text-4xl mb-4">⚠</div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">License limit reached</h2>
        <p class="text-gray-600 text-sm mb-6" x-text="licenseBlockMessage"></p>
        <a href="{{ route('backoffice.dashboard') }}" class="inline-block px-4 py-2 bg-gray-200 rounded-lg text-sm font-medium hover:bg-gray-300">Leave cashier</a>
        <button @click="retryTerminalSession()" class="ml-2 inline-block px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Retry</button>
    </div>
</div>

<!-- INACTIVE LICENSE -->
<div x-show="!licenseActive && !licenseBlocked" class="fixed inset-0 bg-slate-900/95 z-[240] flex items-center justify-center p-6" x-cloak>
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 text-center">
        <h2 class="text-xl font-bold text-gray-900 mb-2">Branch license inactive</h2>
        <p class="text-gray-600 text-sm mb-6">{{ $licenseInactiveNote ?? 'POS is unavailable until your administrator reactivates this branch license.' }}</p>
        <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form-license').submit();" class="inline-block px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium">Logout</a>
        <form id="logout-form-license" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
    </div>
</div>

<!-- STORE DATA DOWNLOAD -->
<div x-show="storeDownload.active && !silentSync" class="fixed inset-0 bg-slate-900/92 z-[205] flex items-center justify-center p-6" x-cloak>
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 text-center">
        <h2 class="text-xl font-bold text-gray-900 mb-2" x-text="storeDownload.title">Preparing POS…</h2>
        <p class="text-gray-600 text-sm mb-5" x-text="storeDownload.message">Downloading catalog…</p>
        <div class="w-full bg-gray-200 rounded-full h-2.5 mb-2 overflow-hidden">
            <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-300 ease-out"
                 :style="'width:' + Math.min(100, Math.max(0, storeDownload.percent)) + '%'"></div>
        </div>
        <p class="text-xs text-gray-400" x-text="storeDownload.percent + '% complete'"></p>
    </div>
</div>

<!-- MODE SELECTION OVERLAY -->
<div x-show="showModeSelect && !licenseBlocked && licenseActive && posReady" class="fixed inset-0 bg-gradient-to-br from-slate-900 via-blue-900 to-slate-800 z-[200] flex items-center justify-center" x-cloak>
    <div class="text-center max-w-2xl mx-auto px-6">
        <div class="mb-2">
            <span class="text-4xl lg:text-5xl font-extrabold text-white tracking-tight">{{ $brandName }}</span>
        </div>
        <p class="text-blue-200 text-sm lg:text-lg mb-10">Select your register mode to get started</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 lg:gap-8">
            <!-- Cafe / Resto Mode -->
            <button @click="selectMode('cafe')"
                    class="group bg-white/10 hover:bg-white/20 backdrop-blur-sm border-2 border-white/20 hover:border-orange-400 rounded-2xl p-6 lg:p-10 transition-all duration-200 text-left">
                <div class="w-14 h-14 lg:w-16 lg:h-16 rounded-xl bg-orange-500/20 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 lg:w-9 lg:h-9 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"></path></svg>
                </div>
                <h3 class="text-xl lg:text-2xl font-bold text-white mb-2">Cafe / Resto</h3>
                <p class="text-blue-200 text-xs lg:text-sm leading-relaxed">All products displayed in a grid. Tap to add to cart. Best for cafes, restaurants, and food service.</p>
            </button>
            <!-- Retail Mode -->
            <button @click="selectMode('retail')"
                    class="group bg-white/10 hover:bg-white/20 backdrop-blur-sm border-2 border-white/20 hover:border-green-400 rounded-2xl p-6 lg:p-10 transition-all duration-200 text-left">
                <div class="w-14 h-14 lg:w-16 lg:h-16 rounded-xl bg-green-500/20 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 lg:w-9 lg:h-9 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                </div>
                <h3 class="text-xl lg:text-2xl font-bold text-white mb-2">Retail</h3>
                <p class="text-blue-200 text-xs lg:text-sm leading-relaxed">Scan or type barcodes to add items. Products only appear when punched. Best for retail, grocery, and convenience stores.</p>
            </button>
        </div>
    </div>
</div>

<!-- CAMERA SCANNER MODAL -->
<div x-show="showCameraScanner" class="fixed inset-0 bg-black/80 z-[150] flex items-center justify-center" x-cloak x-transition>
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span class="font-bold text-sm lg:text-base text-gray-800">Camera Scanner</span>
            </div>
            <button @click="closeCameraScanner()" class="text-gray-400 hover:text-red-500 p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="relative bg-black aspect-video">
            <video id="cameraScanVideo" class="w-full h-full object-cover" playsinline muted></video>
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div class="w-56 h-36 lg:w-72 lg:h-44 border-2 border-white/60 rounded-xl"></div>
            </div>
            <div class="absolute bottom-3 left-0 right-0 text-center text-white/80 text-xs lg:text-sm font-medium">
                Point camera at barcode or QR code
            </div>
        </div>
        <div class="px-4 py-3 flex gap-2">
            <button @click="closeCameraScanner()" class="flex-1 py-2.5 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300">Cancel</button>
        </div>
    </div>
</div>

<!-- TOAST NOTIFICATIONS -->
<div class="fixed top-2 right-2 xl:top-4 xl:right-4 z-[100] space-y-1.5">
    <template x-for="(toast, i) in toasts" :key="toast.id">
        <div class="toast-enter flex items-center gap-2 px-3 py-2 xl:px-4 xl:py-2.5 rounded-lg shadow-lg text-xs xl:text-sm font-medium max-w-[250px] xl:max-w-xs"
             :class="{
                 'bg-green-600 text-white': toast.type === 'success',
                 'bg-red-600 text-white': toast.type === 'error',
                 'bg-yellow-500 text-white': toast.type === 'warning',
                 'bg-blue-600 text-white': toast.type === 'info',
             }">
            <span x-text="toast.message"></span>
        </div>
    </template>
</div>

<!-- OFFLINE BANNER -->
<div id="offline-indicator" x-show="!silentSync && (offlineBanner || !browserOnline)" x-cloak
     class="border-b px-3 py-1.5 text-center text-xs flex-shrink-0"
     :class="browserOnline && !offlineBanner ? 'online border-emerald-200' : 'offline border-amber-200 bg-amber-50 text-amber-900'">
    <span class="font-medium" x-text="browserOnline ? 'Offline mode' : 'No network'"></span>
    <span> — using cached store data. Sales sync when connected.</span>
</div>

<!-- CATALOG BACKGROUND IMPORT (native SQLite) -->
<div x-show="hasNativeBridge && catalogImport.active" x-cloak
     class="border-b border-blue-200 bg-blue-50 px-3 py-1 text-center text-xs text-blue-800 flex-shrink-0">
    <span class="font-medium" x-text="catalogImport.message || 'Catalog updating…'"></span>
    <span x-show="catalogImport.progress > 0" x-text="' (' + catalogImport.progress + '%)'"></span>
    <span> — sales continue from local cache.</span>
</div>

<!-- HEADER -->
<header class="bg-white shadow px-2 py-1 lg:px-4 lg:py-2 flex items-center justify-between flex-shrink-0">
    <h1 class="text-sm lg:text-lg font-bold text-gray-800 whitespace-nowrap">{{ $brandName }}</h1>
    <div class="flex items-center gap-1.5 lg:gap-3 text-[11px] lg:text-sm text-gray-600 flex-nowrap justify-end min-w-0 overflow-x-auto">
        <!-- Native dashboard strip -->
        <div x-show="hasNativeBridge && dashboardData" class="hidden lg:flex items-center gap-2 px-2 py-0.5 rounded-full border border-slate-200 bg-slate-50 text-[10px] text-slate-600"
             :title="'Cached products: ' + (dashboardData?.products_cached || 0)">
            <span x-text="'Today: ' + (dashboardData?.sales_today ?? '—') + ' sales'"></span>
            <template x-if="canViewShiftTotals()">
                <span class="contents">
                    <span class="text-slate-300">|</span>
                    <span x-text="'₱' + parseFloat(dashboardData?.revenue_today || 0).toFixed(0)"></span>
                </span>
            </template>
        </div>

        <!-- Sync Status (hidden on native — background sync is silent) -->
        <div id="sync-status" x-show="!silentSync" class="flex items-center gap-1 lg:gap-1.5 px-1.5 py-0.5 lg:px-2 lg:py-1 rounded-full border cursor-pointer"
             :class="{
                 'border-green-200 bg-green-50': syncStatus === 'synced',
                 'border-yellow-200 bg-yellow-50': syncStatus === 'syncing' || syncStatus === 'pushing' || syncStatus === 'pulling-products' || syncStatus === 'pulling-customers' || syncStatus === 'pulling-inventory' || syncStatus === 'downloading',
                 'border-red-200 bg-red-50': syncStatus === 'offline',
                 'border-gray-200 bg-gray-50': syncStatus === 'partial' || syncStatus === 'error',
             }"
             @click="manualSync()" :title="syncStatusTitle">
            <span class="status-dot"
                  :class="{ 'online': syncStatus === 'synced', 'syncing': syncStatus === 'syncing' || syncStatus === 'pushing' || syncStatus === 'pulling-products' || syncStatus === 'pulling-customers' || syncStatus === 'pulling-inventory' || syncStatus === 'downloading', 'offline': syncStatus === 'offline' || syncStatus === 'error' }"></span>
            <span class="text-[10px] lg:text-xs font-medium"
                  :class="{ 'text-green-700': syncStatus === 'synced', 'text-yellow-700': syncStatus === 'syncing' || syncStatus === 'pushing' || syncStatus === 'pulling-products' || syncStatus === 'pulling-customers' || syncStatus === 'pulling-inventory' || syncStatus === 'downloading', 'text-red-700': syncStatus === 'offline', 'text-gray-500': syncStatus === 'partial' || syncStatus === 'error' }"
                  x-text="syncLabel"></span>
            <span x-show="pendingSyncCount > 0"
                  class="ml-0.5 px-1 py-0.5 text-[9px] lg:text-[10px] font-bold rounded-full bg-yellow-200 text-yellow-800"
                  x-text="pendingSyncCount + ' pending'"></span>
        </div>

        <!-- INSABuddy Status -->
        <div class="flex items-center gap-1 px-1.5 py-0.5 lg:px-2 lg:py-1 rounded-full border"
             :class="buddyConnected ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50'">
            <span class="buddy-dot" :class="buddyConnected ? 'connected' : 'disconnected'"></span>
            <span class="text-[10px] lg:text-xs font-medium" :class="buddyConnected ? 'text-green-700' : 'text-gray-400'"
                  x-text="buddyConnected ? 'INSABuddy' : 'No Buddy'"></span>
        </div>
        <div x-show="hasNativeBridge" class="flex items-center gap-1 px-1.5 py-0.5 lg:px-2 lg:py-1 rounded-full border cursor-help"
             :class="androidLocalUp ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50'"
             :title="androidLocalUp ? 'Android local service OK' : 'Android local service unreachable — scanner/printer may use cloud only'">
            <span class="text-[10px] lg:text-xs font-medium" :class="androidLocalUp ? 'text-green-700' : 'text-amber-700'"
                  x-text="androidLocalUp ? 'Local OK' : 'Local down'"></span>
        </div>
        <!-- Cash drawer — for INSABuddy or native bridge -->
        <button x-show="buddyConnected || hasNativeBridge" @click="openCashDrawer()" class="pos-header-icon rounded-lg hover:bg-gray-100" title="Open Cash Drawer">
            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
        </button>

        <!-- Customer Rewards Scan -->
        <button @click="showRewardsModal = true" class="pos-header-icon rounded-lg hover:bg-gray-100 relative" title="Scan Customer Card / QR for DIY Biz Rewards">
            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path></svg>
            <span x-show="selectedCustomer" class="absolute top-1 right-1 w-2 h-2 bg-green-500 rounded-full"></span>
        </button>

        <!-- Toolbar: clock (recent sales) | printer | scan | gear -->
        <div id="posHeaderToolbar" class="flex items-center gap-0.5 lg:gap-1 flex-shrink-0 flex-nowrap">
            <button @click="showHistoryModal = true; loadRecentSales()" type="button"
                    class="pos-header-icon rounded-lg hover:bg-gray-100 active:bg-gray-200"
                    title="Recent sales" aria-label="Recent sales">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </button>

            <button x-show="hasNativeBridge || buddyConnected" @click="openPrinterSettings()" type="button"
                    class="pos-header-icon rounded-lg hover:bg-gray-100 active:bg-gray-200"
                    title="Printer" aria-label="Printer">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            </button>

            <button @click="scanProduct()" type="button"
                    class="pos-header-icon rounded-lg hover:bg-gray-100 active:bg-gray-200 text-blue-600"
                    title="Scan" aria-label="Scan" :disabled="_scanning">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
            </button>

            <button id="posSettingsGear" x-show="hasNativeBridge || buddyConnected" @click.stop.prevent="openPosSettings()" type="button"
                    class="pos-header-icon rounded-lg hover:bg-gray-100 active:bg-gray-200"
                    title="Settings" aria-label="Settings">
                <svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            </button>

            <span class="font-mono text-[10px] lg:text-xs text-gray-500 tabular-nums min-w-[4.5rem] text-center px-0.5 select-none flex-shrink-0 hidden sm:inline"
                  x-text="headerClock" aria-live="polite"></span>
        </div>

        <!-- Mode Toggle -->
        <button @click="toggleMode()" class="flex items-center gap-1 px-2 py-0.5 lg:px-2.5 lg:py-1 rounded-full text-[10px] lg:text-xs font-semibold border transition-colors"
                :class="posMode === 'cafe' ? 'bg-orange-50 border-orange-300 text-orange-700 hover:bg-orange-100' : 'bg-green-50 border-green-300 text-green-700 hover:bg-green-100'"
                :title="posMode === 'cafe' ? 'Switch to Retail Mode' : 'Switch to Cafe/Resto Mode'">
            <template x-if="posMode === 'cafe'">
                <svg class="w-3 h-3 lg:w-3.5 lg:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"></path></svg>
            </template>
            <template x-if="posMode === 'retail'">
                <svg class="w-3 h-3 lg:w-3.5 lg:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
            </template>
            <span x-text="posMode === 'cafe' ? 'Cafe' : 'Retail'"></span>
        </button>

        @auth
        <span class="font-medium hidden lg:inline">{{ auth()->user()->name }}</span>
        <span class="px-1.5 py-0.5 rounded text-[10px] lg:text-xs font-medium bg-blue-100 text-blue-800">{{ ucfirst(auth()->user()->role) }}</span>
        <span class="hidden xl:inline">Branch: <strong>{{ auth()->user()->branch?->name ?? 'N/A' }}</strong></span>
        @if(auth()->user()->canAccessBackoffice())
        <a href="{{ route('backoffice.dashboard') }}" class="text-blue-600 hover:underline text-[11px] lg:text-sm">Back Office</a>
        @endif
        <form method="POST" action="{{ route('logout') }}" class="inline">@csrf
            <button type="submit" class="text-red-600 hover:underline text-[11px] lg:text-sm">Logout</button>
        </form>
        @endauth
    </div>
</header>

<!-- SHIFT BAR -->
<div class="px-2 pt-1 lg:px-4 lg:pt-2 flex-shrink-0">
    <div x-show="!activeShift" class="bg-yellow-50 border border-yellow-300 rounded-lg p-2 lg:p-3 flex items-center justify-between">
        <div>
            <div class="font-semibold text-yellow-800 text-xs lg:text-base">No Active Shift</div>
            <div class="text-[11px] lg:text-sm text-yellow-600">Open a shift to start processing sales.</div>
        </div>
        <button @click="showShiftOpenModal = true" class="px-3 py-1.5 lg:px-5 lg:py-2 bg-green-600 text-white rounded-lg text-xs lg:text-base font-medium hover:bg-green-700">Open Shift</button>
    </div>
    <div x-show="activeShift" class="bg-green-50 border border-green-300 rounded-lg p-1.5 lg:p-3 flex items-center justify-between gap-2">
        <div class="min-w-0 flex-shrink">
            <div class="font-semibold text-green-800 text-[11px] lg:text-base">Shift Active</div>
            <div class="text-[10px] lg:text-sm text-green-600 truncate">
                Opened: <span x-text="activeShift ? new Date(activeShift.opened_at).toLocaleTimeString() : ''"></span> &middot;
                Opening Cash: &#8369;<span x-text="activeShift ? parseFloat(activeShift.opening_cash).toFixed(2) : '0.00'"></span>
                <template x-if="canViewShiftTotals()">
                    <span> &middot; Total Sales: &#8369;<span x-text="parseFloat(shiftSalesTotal || 0).toFixed(2)"></span></span>
                </template>
            </div>
        </div>
        <div class="flex items-center gap-1 lg:gap-2 flex-shrink-0">
            @if(auth()->user()->hasRole('owner', 'admin', 'manager'))
            <button @click="generateXReading()" class="px-1.5 py-1 lg:px-4 lg:py-2 bg-blue-600 text-white rounded-lg text-[10px] lg:text-sm font-medium hover:bg-blue-700 whitespace-nowrap">X-Reading</button>
            <button @click="generateZReading()" class="px-1.5 py-1 lg:px-4 lg:py-2 bg-orange-600 text-white rounded-lg text-[10px] lg:text-sm font-medium hover:bg-orange-700 whitespace-nowrap">Z-Reading</button>
            @endif
            <button @click="showShiftCloseModal = true" class="px-1.5 py-1 lg:px-4 lg:py-2 bg-red-600 text-white rounded-lg text-[10px] lg:text-sm font-medium hover:bg-red-700 whitespace-nowrap">Close Shift</button>
        </div>
    </div>
</div>

<!-- ═══════════ MAIN POS SCREEN ═══════════ -->
<div x-show="screen === 'pos'" id="posScreen" class="flex flex-1 overflow-hidden p-2 gap-2 lg:p-4 lg:gap-4 pos-content-pad-bottom" :class="!activeShift && 'opacity-40 pointer-events-none'">

    <!-- LEFT: PRODUCTS (Cafe Mode) -->
    <div x-show="posMode === 'cafe'" class="flex-1 flex flex-col min-w-0">
        <div class="flex gap-1.5 lg:gap-2 mb-2 lg:mb-3">
            <div class="relative flex-1">
                <input type="text" data-scan-input
                       @input="onScanFieldInput($event, 'cafe')"
                       @keydown.enter.prevent="commitScanField($event, 'cafe')"
                       placeholder="Search or scan barcode..."
                       autocomplete="off" autocorrect="off" spellcheck="false"
                       x-ref="searchInput" id="posSearchInput"
                       class="w-full p-1.5 pl-7 lg:p-2.5 lg:pl-9 border rounded-lg text-xs lg:text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                <svg class="w-3.5 h-3.5 lg:w-4 lg:h-4 text-gray-400 absolute left-2 top-2 lg:left-3 lg:top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <select x-model="selectedCategory" @change="gridDisplayLimit = 48; _nativeGridOffset = 0; filterProducts()" class="p-1.5 lg:p-2.5 border rounded-lg text-xs lg:text-sm bg-white max-w-[120px] lg:max-w-none">
                <option value="">All Categories</option>
                <template x-for="cat in categories" :key="cat.id">
                    <option :value="cat.id" x-text="cat.name"></option>
                </template>
            </select>
        </div>

        <div class="flex-1 overflow-y-auto">
            <div x-show="productsLoading && filteredProducts.length === 0" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 3xl:grid-cols-6 gap-1.5 lg:gap-2">
                <template x-for="i in 12" :key="'skel-' + i">
                    <div class="bg-white rounded-lg border border-gray-200 p-2 lg:p-3 animate-pulse">
                        <div class="h-3 lg:h-4 bg-gray-200 rounded w-3/4"></div>
                        <div class="h-2 bg-gray-100 rounded w-1/2 mt-2"></div>
                        <div class="flex justify-between mt-3 lg:mt-4">
                            <div class="h-3 bg-gray-200 rounded w-12"></div>
                            <div class="h-3 bg-gray-100 rounded w-10"></div>
                        </div>
                    </div>
                </template>
            </div>
            <div class="product-grid" x-show="!productsLoading || filteredProducts.length > 0">
                <template x-for="product in visibleProducts" :key="product.id">
                    <button @click="addToCart(product)"
                            class="product-tile product-card bg-white rounded-lg shadow hover:shadow-md border border-gray-200 p-2 lg:p-3 text-left transition-all flex flex-col"
                            :class="product.stock <= 0 && 'opacity-40 cursor-not-allowed'"
                            :disabled="product.stock <= 0">
                        <div class="font-medium text-[11px] lg:text-sm leading-tight truncate" x-text="product.name"></div>
                        <div class="text-[10px] lg:text-xs text-gray-400 mt-0.5 lg:mt-1 truncate" x-text="product.sku || product.barcode || ''"></div>
                        <div class="flex flex-wrap gap-0.5 mt-1" x-show="product.near_expiry || product.earliest_expiry">
                            <span class="badge-expiry" x-show="renderExpiryBadge(product)" x-text="renderExpiryBadge(product)"></span>
                        </div>
                        <div class="mt-auto pt-1.5 lg:pt-2 flex items-end justify-between gap-1">
                            <span class="font-bold text-blue-700 text-[11px] lg:text-sm" x-text="'₱' + parseFloat(product.price).toFixed(2)"></span>
                            <span class="text-[9px] lg:text-xs px-1 lg:px-1.5 py-0.5 rounded"
                                  :class="stockBadgeClass(product)"
                                  x-text="stockBadgeText(product)"></span>
                        </div>
                    </button>
                </template>
            </div>
            <div x-show="!productsLoading && catalogNeedsCategory" class="text-center py-8 lg:py-12 text-amber-700 text-xs lg:text-sm px-4">
                Large catalog — choose a <strong>category</strong> above or search (3+ characters) to load products.
            </div>
            <div x-show="!productsLoading && !catalogNeedsCategory && filteredProducts.length === 0" class="text-center py-8 lg:py-12 text-gray-400 text-xs lg:text-sm">No products found.</div>
            <div x-show="gridCanLoadMore" class="text-center py-3">
                <button type="button" @click="loadMoreGrid()"
                        class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-xs lg:text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm">
                    Load more (<span x-text="filteredProducts.length"></span> of <span x-text="filteredProductsTotal"></span>)
                </button>
            </div>
        </div>
    </div>

    <!-- LEFT: RETAIL MODE (Scan & Punch) -->
    <div x-show="posMode === 'retail'" class="flex-1 flex flex-col min-w-0">
        <!-- Scan Bar + Preview Toggle -->
        <div class="mb-3 lg:mb-4">
            <div class="flex gap-2 items-stretch">
                <div class="relative flex-1">
                    <input type="text" id="retailScanInput" data-scan-input
                           @input="onScanFieldInput($event, 'retail')"
                           @keydown.enter.prevent="commitScanField($event, 'retail')"
                           :placeholder="retailPreviewMode ? 'Preview mode — scan barcode or type product...' : 'Scan barcode or type product name...'"
                           autocomplete="off" autocorrect="off" spellcheck="false"
                           class="w-full p-3 pl-10 lg:p-4 lg:pl-12 border-2 rounded-xl text-sm lg:text-lg focus:ring-2 focus:outline-none font-medium"
                           :class="retailPreviewMode ? 'border-amber-400 bg-amber-50/50 focus:ring-amber-500 focus:border-amber-500' : 'border-green-400 bg-green-50/50 focus:ring-green-500 focus:border-green-500'">
                    <svg class="w-5 h-5 lg:w-6 lg:h-6 absolute left-3 top-3.5 lg:left-4 lg:top-4" :class="retailPreviewMode ? 'text-amber-500' : 'text-green-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                    <button hidden data-scan-clear @click="retailCancel()"
                            class="absolute right-3 top-3 lg:right-4 lg:top-3.5 text-gray-400 hover:text-red-500 p-0.5">
                        <svg class="w-5 h-5 lg:w-6 lg:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <button @click="scanProduct()" :disabled="_scanning"
                        class="px-3 lg:px-4 rounded-xl border-2 border-blue-300 bg-blue-50 text-blue-600 hover:bg-blue-100 font-semibold text-xs lg:text-sm whitespace-nowrap transition-colors flex items-center gap-1.5"
                        title="Scan using camera or connected scanner">
                    <svg class="w-4 h-4 lg:w-5 lg:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Scan
                </button>
                <button @click="retailPreviewMode = !retailPreviewMode; retailCancel()"
                        class="px-3 lg:px-4 rounded-xl border-2 font-semibold text-xs lg:text-sm whitespace-nowrap transition-colors flex items-center gap-1.5"
                        :class="retailPreviewMode ? 'border-amber-400 bg-amber-100 text-amber-700 hover:bg-amber-200' : 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50'"
                        :title="retailPreviewMode ? 'Preview ON — scans show product details first' : 'Preview OFF — scans add directly to cart'">
                    <svg class="w-4 h-4 lg:w-5 lg:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    Preview
                </button>
            </div>
        </div>

        <!-- Scanned Product Preview (only when Preview mode is on) -->
        <template x-if="retailPreviewMode && retailScanResult">
            <div class="bg-white rounded-xl shadow-lg border-2 border-green-300 p-4 lg:p-6 mb-4 animate-in">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <div class="text-lg lg:text-2xl font-bold text-gray-800" x-text="retailScanResult.name"></div>
                        <div class="text-sm lg:text-base text-gray-400 mt-1" x-text="retailScanResult.sku + (retailScanResult.barcode ? ' · ' + retailScanResult.barcode : '')"></div>
                        <div class="flex items-center gap-3 mt-3">
                            <span class="text-2xl lg:text-3xl font-extrabold text-blue-700" x-text="'₱' + parseFloat(retailScanResult.price).toFixed(2)"></span>
                            <span class="text-sm lg:text-base px-2 py-1 rounded-lg"
                                  :class="stockBadgeClass(retailScanResult)"
                                  x-text="stockBadgeText(retailScanResult)"></span>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <button @click="retailAddToCart(retailScanResult)" :disabled="retailScanResult.stock <= 0"
                                class="px-5 py-2.5 lg:px-8 lg:py-3 bg-green-600 text-white rounded-xl text-sm lg:text-base font-bold hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">
                            Add to Cart
                        </button>
                        <button @click="retailCancel()"
                                class="px-5 py-2 lg:px-8 lg:py-2.5 bg-gray-100 text-gray-600 rounded-xl text-sm lg:text-base font-medium hover:bg-gray-200 transition-colors">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Search Results (tap to add) -->
        <div x-show="!retailScanResult && filteredProducts.length > 0" class="mb-3">
            <div class="text-xs text-gray-500 mb-1.5 font-medium" x-text="filteredProducts.length + ' result' + (filteredProducts.length > 1 ? 's' : '') + ' — tap to add'"></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-48 lg:max-h-60 overflow-y-auto">
                <template x-for="product in filteredProducts.slice(0, 30)" :key="product.id">
                    <button @click="retailAddToCart(product)"
                            class="bg-white rounded-lg shadow border border-gray-200 p-2 lg:p-3 text-left hover:border-green-400 hover:shadow-md transition-all flex items-center gap-2"
                            :class="product.stock <= 0 && 'opacity-40 cursor-not-allowed'"
                            :disabled="product.stock <= 0">
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-xs lg:text-sm truncate" x-text="product.name"></div>
                            <div class="text-[10px] lg:text-xs text-gray-400" x-text="product.sku"></div>
                        </div>
                        <span class="font-bold text-blue-700 text-xs lg:text-sm whitespace-nowrap" x-text="'₱' + parseFloat(product.price).toFixed(2)"></span>
                    </button>
                </template>
            </div>
        </div>

        <!-- Punched Items List (the cart view for retail mode) -->
        <div class="flex-1 overflow-y-auto bg-white rounded-xl shadow">
            <div x-show="cart.length === 0" class="flex flex-col items-center justify-center h-full py-16 text-gray-300">
                <svg class="w-16 h-16 lg:w-20 lg:h-20 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                <div class="text-sm lg:text-base font-medium">Scan or type a barcode to begin</div>
                <div class="text-xs lg:text-sm mt-1">Items will appear here as they are punched</div>
            </div>
            <table x-show="cart.length > 0" class="w-full text-xs lg:text-sm">
                <thead class="bg-gray-50 sticky top-0 border-b">
                    <tr>
                        <th class="text-left p-2 lg:p-3 font-semibold text-gray-600 w-8">#</th>
                        <th class="text-left p-2 lg:p-3 font-semibold text-gray-600">Item</th>
                        <th class="text-center p-2 lg:p-3 font-semibold text-gray-600 w-20">Qty</th>
                        <th class="text-right p-2 lg:p-3 font-semibold text-gray-600 w-24">Price</th>
                        <th class="text-right p-2 lg:p-3 font-semibold text-gray-600 w-28">Total</th>
                        <th class="w-8"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(item, idx) in cart" :key="item.product_id">
                        <tr class="border-b hover:bg-blue-50/50">
                            <td class="p-2 lg:p-3 text-gray-400 font-mono" x-text="idx + 1"></td>
                            <td class="p-2 lg:p-3">
                                <div class="font-medium" x-text="item.product_name"></div>
                                <div class="text-[10px] lg:text-xs text-gray-400" x-text="item.sku || item.barcode || ''"></div>
                            </td>
                            <td class="p-2 lg:p-3 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button @click="changeQty(idx, -1)" class="w-5 h-5 lg:w-6 lg:h-6 rounded bg-gray-200 hover:bg-gray-300 text-xs font-bold flex items-center justify-center">-</button>
                                    <span class="font-bold w-6 lg:w-8 text-center" x-text="item.qty"></span>
                                    <button @click="changeQty(idx, 1)" class="w-5 h-5 lg:w-6 lg:h-6 rounded bg-gray-200 hover:bg-gray-300 text-xs font-bold flex items-center justify-center">+</button>
                                </div>
                            </td>
                            <td class="p-2 lg:p-3 text-right font-mono" x-text="'₱' + item.price.toFixed(2)"></td>
                            <td class="p-2 lg:p-3 text-right font-mono font-bold" x-text="'₱' + ((item.qty * item.price) - (item.discount || 0)).toFixed(2)"></td>
                            <td class="p-2 lg:p-3">
                                <button @click="removeItem(idx)" class="text-red-400 hover:text-red-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Retail Mode Footer: Customer + Totals + Pay (fixed above system nav) -->
        <div x-show="posMode === 'retail'" class="pos-fixed-bottom px-2 py-1.5 lg:px-4 lg:py-2">
            <div class="flex items-center gap-2 lg:gap-3">
                <!-- Customer -->
                <div class="relative w-40 lg:w-52 flex-shrink-0">
                    <input type="text" x-model="customerSearch" @input.debounce.300ms="searchCustomers()"
                           @focus="showCustomerDropdown = true"
                           :placeholder="selectedCustomer ? selectedCustomer.name : 'Customer (optional)'"
                           :class="selectedCustomer ? 'border-green-300 bg-green-50' : 'border-gray-200'"
                           class="w-full p-1.5 lg:p-2 border rounded-lg text-[11px] lg:text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <button x-show="selectedCustomer" @click="selectedCustomer = null; customerSearch = ''"
                            class="absolute right-1.5 top-1.5 lg:right-2 lg:top-2 text-gray-400 hover:text-red-500">
                        <svg class="w-3.5 h-3.5 lg:w-4 lg:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                    <div x-show="showCustomerDropdown && customerResults.length > 0" @click.away="showCustomerDropdown = false"
                         class="absolute z-20 w-full bg-white border rounded-lg shadow-lg max-h-32 overflow-y-auto bottom-full mb-1">
                        <template x-for="c in customerResults" :key="c.id">
                            <button @click="selectCustomer(c)"
                                    class="w-full text-left px-2 py-1.5 hover:bg-blue-50 border-b last:border-0 text-[11px] lg:text-sm">
                                <div class="font-medium" x-text="c.name"></div>
                                <div class="text-[10px] text-gray-400" x-text="c.phone || c.email || ''"></div>
                            </button>
                        </template>
                    </div>
                </div>
                <!-- Clear -->
                <button x-show="cart.length > 0" @click="clearCart()"
                        class="text-[10px] lg:text-xs text-red-500 hover:text-red-700 px-1.5 py-0.5 rounded hover:bg-red-50 whitespace-nowrap flex-shrink-0">Clear All</button>
                <!-- Spacer -->
                <div class="flex-1"></div>
                <!-- Subtotal / Discount -->
                <div class="text-[10px] lg:text-xs text-right flex-shrink-0 hidden sm:block">
                    <div class="text-gray-500">Sub: <span class="font-medium text-gray-700" x-text="'₱' + cartSubtotal.toFixed(2)"></span></div>
                    <div class="text-gray-500 cursor-pointer hover:text-blue-600" @click="showOrderDiscountModal = true">Disc: <span class="text-red-500" x-text="'- ₱' + cartDiscount.toFixed(2)"></span></div>
                </div>
                <!-- Total -->
                <div class="text-right flex-shrink-0">
                    <div class="text-[9px] lg:text-[10px] text-gray-400 uppercase">Total</div>
                    <div class="text-lg lg:text-2xl font-extrabold text-blue-700 leading-tight" x-text="'₱' + cartTotal.toFixed(2)"></div>
                </div>
                <!-- Pay Button -->
                <button @click="goToCheckout()" :disabled="cart.length === 0"
                        class="px-5 py-2 lg:px-8 lg:py-2.5 rounded-xl text-white font-bold text-sm lg:text-base transition-colors whitespace-nowrap flex-shrink-0"
                        :class="cart.length > 0 ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-300 cursor-not-allowed'">
                    Pay &amp; Complete
                </button>
            </div>
        </div>
    </div>

    <!-- RIGHT: CART (Cafe mode only; Retail mode has built-in item list + footer) -->
    <div x-show="posMode === 'cafe'" class="w-56 lg:w-80 xl:w-96 bg-white rounded-lg shadow flex flex-col flex-shrink-0 overflow-hidden">
        <div class="p-2 lg:p-4 border-b flex-shrink-0">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-sm lg:text-lg">Cart</h2>
                <div class="flex items-center gap-1.5 lg:gap-2">
                    <span class="text-[10px] lg:text-sm text-gray-500" x-text="cart.length + ' item(s)'"></span>
                    <button x-show="cart.length > 0" @click="clearCart()"
                            class="text-[10px] lg:text-xs text-red-500 hover:text-red-700 px-1.5 py-0.5 rounded hover:bg-red-50">Clear</button>
                </div>
            </div>
            <!-- Customer Selection -->
            <div class="mt-1.5 lg:mt-2 relative">
                <div class="flex items-center gap-1.5">
                    <div class="flex-1 relative">
                        <input type="text" x-model="customerSearch" @input.debounce.300ms="searchCustomers()"
                               @focus="showCustomerDropdown = true"
                               :placeholder="selectedCustomer ? selectedCustomer.name : 'Customer (optional)'"
                               :class="selectedCustomer ? 'border-green-300 bg-green-50' : 'border-gray-200'"
                               class="w-full p-1.5 lg:p-2 border rounded-lg text-[11px] lg:text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        <button x-show="selectedCustomer" @click="selectedCustomer = null; customerSearch = ''"
                                class="absolute right-1.5 top-1.5 lg:right-2 lg:top-2 text-gray-400 hover:text-red-500">
                            <svg class="w-3.5 h-3.5 lg:w-4 lg:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                </div>
                <div x-show="showCustomerDropdown && customerResults.length > 0" @click.away="showCustomerDropdown = false"
                     class="absolute z-20 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-32 lg:max-h-40 overflow-y-auto">
                    <template x-for="c in customerResults" :key="c.id">
                        <button @click="selectCustomer(c)"
                                class="w-full text-left px-2 py-1.5 lg:px-3 lg:py-2 hover:bg-blue-50 border-b last:border-0 text-[11px] lg:text-sm">
                            <div class="font-medium" x-text="c.name"></div>
                            <div class="text-[10px] lg:text-xs text-gray-400" x-text="c.phone || c.email || ''"></div>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-1.5 lg:p-3 space-y-1 lg:space-y-2">
            <template x-for="(item, idx) in cart" :key="item.product_id">
                <div class="bg-gray-50 rounded-lg p-2 lg:p-3 flex gap-2 lg:gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-[11px] lg:text-sm truncate" x-text="item.product_name"></div>
                        <div class="text-[10px] lg:text-xs text-gray-400" x-text="'₱' + item.price.toFixed(2) + ' each'"></div>
                        <div class="flex items-center gap-1.5 lg:gap-2 mt-1 lg:mt-1.5">
                            <button @click="changeQty(idx, -1)" class="w-5 h-5 lg:w-6 lg:h-6 rounded bg-gray-200 hover:bg-gray-300 text-[11px] lg:text-sm font-bold flex items-center justify-center">-</button>
                            <span class="font-bold text-[11px] lg:text-sm w-6 lg:w-8 text-center" x-text="item.qty"></span>
                            <button @click="changeQty(idx, 1)" class="w-5 h-5 lg:w-6 lg:h-6 rounded bg-gray-200 hover:bg-gray-300 text-[11px] lg:text-sm font-bold flex items-center justify-center">+</button>
                            <button @click="openItemDiscount(idx)" class="ml-0.5 text-[10px] lg:text-xs text-blue-500 hover:text-blue-700 hover:underline"
                                    x-text="item.discount > 0 ? '-₱' + item.discount.toFixed(2) : 'Disc.'"></button>
                        </div>
                    </div>
                    <div class="text-right flex flex-col items-end justify-between">
                        <button @click="removeItem(idx)" class="text-red-400 hover:text-red-600 text-[10px] lg:text-xs">Remove</button>
                        <span class="font-bold text-[11px] lg:text-sm" x-text="'₱' + ((item.qty * item.price) - (item.discount || 0)).toFixed(2)"></span>
                    </div>
                </div>
            </template>
            <div x-show="cart.length === 0" class="text-center py-6 lg:py-8 text-gray-300 text-[11px] lg:text-sm" x-text="posMode === 'retail' ? 'Cart is empty. Scan items to add.' : 'Cart is empty. Tap products to add.'"></div>
        </div>

        <!-- Pinned cart footer — fixed above system nav (cafe mode) -->
        <div x-show="posMode === 'cafe'" class="pos-fixed-bottom p-2 lg:p-4 space-y-0.5 lg:space-y-2">
            <div class="flex justify-between text-[11px] lg:text-sm"><span class="text-gray-500">Subtotal</span><span x-text="'₱' + cartSubtotal.toFixed(2)"></span></div>
            <div class="flex justify-between text-[11px] lg:text-sm">
                <span class="text-gray-500 cursor-pointer hover:text-blue-600" @click="showOrderDiscountModal = true">Discount <span class="text-[9px] lg:text-xs">(tap)</span></span>
                <span class="text-red-500" x-text="'- ₱' + cartDiscount.toFixed(2)"></span>
            </div>
            <div class="flex justify-between text-base lg:text-xl font-bold border-t pt-1 lg:pt-2"><span>Total</span><span class="text-blue-700" x-text="'₱' + cartTotal.toFixed(2)"></span></div>
            <button @click="goToCheckout()" :disabled="cart.length === 0"
                    class="w-full py-2 lg:py-3 rounded-lg text-white font-bold text-sm lg:text-lg transition-colors"
                    :class="cart.length > 0 ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-300 cursor-not-allowed'">
                Pay &amp; Complete
            </button>
        </div>
    </div>
</div>

<!-- ═══════════ CHECKOUT SCREEN ═══════════ -->
<div x-show="screen === 'checkout'" id="checkoutScreen" class="flex flex-1 overflow-hidden p-2 gap-2 lg:p-4 lg:gap-4 pos-content-pad-bottom">

    <!-- LEFT: ORDER REVIEW -->
    <div class="flex-1 bg-white rounded-lg shadow flex flex-col min-w-0 overflow-hidden">
        <div class="p-2 lg:p-4 border-b flex items-center justify-between flex-shrink-0">
            <h2 class="font-bold text-sm lg:text-xl">Order Review</h2>
            <button @click="screen = 'pos'" class="text-blue-600 hover:underline text-[11px] lg:text-sm">&larr; Back to POS</button>
        </div>
        <div class="flex-1 overflow-y-auto">
            <table class="w-full text-[11px] lg:text-sm">
                <thead class="bg-gray-50 sticky top-0">
                    <tr>
                        <th class="text-left p-1.5 lg:p-3 font-medium">#</th>
                        <th class="text-left p-1.5 lg:p-3 font-medium">Product</th>
                        <th class="text-center p-1.5 lg:p-3 font-medium">Qty</th>
                        <th class="text-right p-1.5 lg:p-3 font-medium">Price</th>
                        <th class="text-right p-1.5 lg:p-3 font-medium">Disc.</th>
                        <th class="text-right p-1.5 lg:p-3 font-medium">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(item, idx) in cart" :key="item.product_id">
                        <tr class="border-t hover:bg-gray-50">
                            <td class="p-1.5 lg:p-3 text-gray-400" x-text="idx + 1"></td>
                            <td class="p-1.5 lg:p-3">
                                <div class="font-medium" x-text="item.product_name"></div>
                                <div class="text-[10px] lg:text-xs text-gray-400" x-text="item.sku || ''"></div>
                            </td>
                            <td class="p-1.5 lg:p-3 text-center font-bold" x-text="item.qty"></td>
                            <td class="p-1.5 lg:p-3 text-right font-mono" x-text="'₱' + item.price.toFixed(2)"></td>
                            <td class="p-1.5 lg:p-3 text-right font-mono text-red-500" x-text="'₱' + (item.discount || 0).toFixed(2)"></td>
                            <td class="p-1.5 lg:p-3 text-right font-mono font-bold" x-text="'₱' + ((item.qty * item.price) - (item.discount || 0)).toFixed(2)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <!-- Pinned order summary — always visible -->
        <div class="p-2 lg:p-4 border-t bg-gray-50 flex-shrink-0">
            <div x-show="selectedCustomer" class="text-[11px] lg:text-sm text-gray-600 mb-1 lg:mb-2">
                Customer: <strong x-text="selectedCustomer?.name"></strong>
                <span class="text-[10px] lg:text-xs text-gray-400" x-text="selectedCustomer?.phone || ''"></span>
            </div>
            <div class="grid grid-cols-3 gap-2 lg:gap-4 text-center">
                <div>
                    <div class="text-[9px] lg:text-xs text-gray-500 uppercase">Subtotal</div>
                    <div class="text-sm lg:text-lg font-bold" x-text="'₱' + cartSubtotal.toFixed(2)"></div>
                </div>
                <div>
                    <div class="text-[9px] lg:text-xs text-gray-500 uppercase">Discount</div>
                    <div class="text-sm lg:text-lg font-bold text-red-500" x-text="'₱' + cartDiscount.toFixed(2)"></div>
                </div>
                <div>
                    <div class="text-[9px] lg:text-xs text-gray-500 uppercase">Grand Total</div>
                    <div class="text-lg lg:text-2xl font-bold text-blue-700" x-text="'₱' + cartTotal.toFixed(2)"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: PAYMENT PANEL -->
    <div class="w-64 lg:w-96 bg-white rounded-lg shadow flex flex-col flex-shrink-0 overflow-hidden">
        <!-- Scrollable content area -->
        <div class="flex-1 overflow-y-auto">
            <div class="p-2 lg:p-4 border-b">
                <h3 class="font-bold text-sm lg:text-lg mb-1.5 lg:mb-3">Payment Method</h3>
                <div class="grid grid-cols-3 lg:grid-cols-2 gap-1 lg:gap-2">
                    <template x-for="m in paymentMethods" :key="m.value">
                        <button @click="paymentMethod = m.value"
                                class="p-1.5 lg:p-3 rounded-lg border-2 text-[10px] lg:text-sm font-medium transition-all text-center"
                                :class="paymentMethod === m.value ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-gray-200 hover:border-gray-300 text-gray-600'">
                            <div class="text-xs lg:text-lg mb-0.5" x-text="m.icon"></div>
                            <div x-text="m.label"></div>
                        </button>
                    </template>
                </div>
            </div>

            <div class="p-2 lg:p-4">
                <div class="space-y-2 lg:space-y-4">
                    <div>
                        <label class="block text-[11px] lg:text-sm font-medium text-gray-600 mb-0.5 lg:mb-1">Amount Due</label>
                        <div class="text-xl lg:text-3xl font-bold text-blue-700" x-text="'₱' + cartTotal.toFixed(2)"></div>
                    </div>

                    <div x-show="paymentMethod === 'cash'">
                        <label class="block text-[11px] lg:text-sm font-medium text-gray-600 mb-0.5 lg:mb-1">Cash Received</label>
                        <input type="number" x-model.number="amountTendered" step="0.01" min="0"
                               class="w-full p-2 lg:p-3 border-2 rounded-lg text-lg lg:text-2xl font-bold text-center focus:border-blue-500 focus:outline-none"
                               placeholder="0.00" @input="calculateChange()" x-ref="cashInput">
                        <div class="grid grid-cols-4 gap-1 lg:gap-2 mt-1 lg:mt-2">
                            <button @click="amountTendered = cartTotal; calculateChange()"
                                    class="py-1 lg:py-2 text-[10px] lg:text-sm font-medium bg-green-100 text-green-700 rounded hover:bg-green-200 transition-colors">
                                Exact
                            </button>
                            <template x-for="amt in quickCashAmounts" :key="amt">
                                <button @click="amountTendered = amt; calculateChange()"
                                        class="py-1 lg:py-2 text-[10px] lg:text-sm font-medium bg-gray-100 rounded hover:bg-gray-200 transition-colors"
                                        x-text="'₱' + amt"></button>
                            </template>
                        </div>
                    </div>

                    <div x-show="paymentMethod !== 'cash'">
                        <label class="block text-[11px] lg:text-sm font-medium text-gray-600 mb-0.5 lg:mb-1">Reference Number (optional)</label>
                        <input type="text" x-model="paymentRef" class="w-full p-2 lg:p-3 border rounded-lg text-xs lg:text-sm" placeholder="Transaction ref...">
                    </div>

                    <div x-show="paymentMethod === 'cash' && amountTendered > 0" class="bg-green-50 border border-green-200 rounded-lg p-1.5 lg:p-4 text-center">
                        <div class="text-[10px] lg:text-sm text-green-600 font-medium">Change</div>
                        <div class="text-lg lg:text-3xl font-bold" :class="changeAmount >= 0 ? 'text-green-700' : 'text-red-600'"
                             x-text="'₱' + Math.abs(changeAmount).toFixed(2)"></div>
                        <div x-show="changeAmount < 0" class="text-[10px] lg:text-xs text-red-500 mt-0.5">Insufficient amount</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pinned bottom button — fixed above system nav -->
        <div class="pos-fixed-bottom p-2 lg:p-4">
            <button @click="completeSale()" :disabled="!canProceed"
                    class="w-full py-2.5 lg:py-4 rounded-lg text-white font-bold text-sm lg:text-xl transition-colors"
                    :class="canProceed ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-300 cursor-not-allowed'">
                <span x-show="!saleProcessing">Complete Sale</span>
                <span x-show="saleProcessing">Processing…</span>
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════ MODALS ═══════════════ -->

<!-- SHIFT OPEN MODAL -->
<div x-show="showShiftOpenModal" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50" x-transition>
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[320px] lg:max-w-sm p-4 lg:p-6" @click.away="showShiftOpenModal = false">
        <h2 class="text-base lg:text-xl font-bold text-green-700 mb-3 lg:mb-4">Open Shift</h2>
        <label class="block text-[11px] lg:text-sm font-medium text-gray-600 mb-1.5 lg:mb-2">Opening Cash Amount</label>
        <div class="relative">
            <span class="absolute left-3 top-2 lg:top-3 text-base lg:text-lg font-bold text-gray-400">₱</span>
            <input type="number" x-model.number="shiftCashInput" step="0.01" min="0"
                   class="w-full p-2 pl-7 lg:p-3 lg:pl-8 border-2 rounded-lg text-lg lg:text-xl font-bold focus:border-green-500 focus:outline-none"
                   placeholder="0.00" @keydown.enter="openShift()" x-ref="shiftOpenInput">
        </div>
        <div class="grid grid-cols-3 gap-1.5 lg:gap-2 mt-2 lg:mt-3">
            <template x-for="amt in [500, 1000, 2000, 3000, 5000, 10000]" :key="amt">
                <button @click="shiftCashInput = amt"
                        class="py-1.5 lg:py-2 text-[11px] lg:text-sm font-medium bg-gray-100 rounded hover:bg-gray-200"
                        x-text="'₱' + amt.toLocaleString()"></button>
            </template>
        </div>
        <div class="flex gap-2 lg:gap-3 mt-4 lg:mt-5">
            <button @click="openShift()" :disabled="!shiftCashInput || shiftCashInput < 0"
                    class="flex-1 py-2 lg:py-3 bg-green-600 text-white rounded-lg text-xs lg:text-base font-medium hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed">
                Open Shift
            </button>
            <button @click="showShiftOpenModal = false; shiftCashInput = 0" class="flex-1 py-2 lg:py-3 bg-gray-200 text-gray-700 rounded-lg text-xs lg:text-base font-medium hover:bg-gray-300">Cancel</button>
        </div>
    </div>
</div>

<!-- SHIFT CLOSE MODAL -->
<div x-show="showShiftCloseModal" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50" x-transition>
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[320px] lg:max-w-sm p-4 lg:p-6" @click.away="showShiftCloseModal = false">
        <h2 class="text-base lg:text-xl font-bold text-red-700 mb-3 lg:mb-4">Close Shift</h2>
        <label class="block text-[11px] lg:text-sm font-medium text-gray-600 mb-1.5 lg:mb-2">Closing Cash Amount</label>
        <div class="relative">
            <span class="absolute left-3 top-2 lg:top-3 text-base lg:text-lg font-bold text-gray-400">₱</span>
            <input type="number" x-model.number="shiftCashInput" step="0.01" min="0"
                   class="w-full p-2 pl-7 lg:p-3 lg:pl-8 border-2 rounded-lg text-lg lg:text-xl font-bold focus:border-red-500 focus:outline-none"
                   placeholder="0.00" @keydown.enter="doCloseShift()">
        </div>
        <div class="flex gap-2 lg:gap-3 mt-4 lg:mt-5">
            <button @click="doCloseShift()" :disabled="shiftCashInput === null || shiftCashInput === '' || shiftCashInput < 0"
                    class="flex-1 py-2 lg:py-3 bg-red-600 text-white rounded-lg text-xs lg:text-base font-medium hover:bg-red-700 disabled:bg-gray-300 disabled:cursor-not-allowed">
                Close Shift
            </button>
            <button @click="showShiftCloseModal = false; shiftCashInput = 0" class="flex-1 py-2 lg:py-3 bg-gray-200 text-gray-700 rounded-lg text-xs lg:text-base font-medium hover:bg-gray-300">Cancel</button>
        </div>
    </div>
</div>

<!-- SHIFT CLOSE RESULT MODAL -->
<div x-show="showShiftResult" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50" x-transition>
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[320px] lg:max-w-sm p-4 lg:p-6 text-center">
        <h2 class="text-base lg:text-xl font-bold text-gray-800 mb-3 lg:mb-4">Shift Closed</h2>
        <div class="space-y-2 lg:space-y-3 text-[11px] lg:text-sm text-left">
            @if(auth()->user()?->canViewShiftTotals())
            <div class="flex justify-between py-1.5 lg:py-2 border-b"><span class="text-gray-600">Total Sales</span><span class="font-bold" x-text="'₱' + parseFloat(shiftResultData?.system_sales_total || 0).toFixed(2)"></span></div>
            @endif
            <div class="flex justify-between py-1.5 lg:py-2 border-b"><span class="text-gray-600">Opening Cash</span><span class="font-bold" x-text="'₱' + parseFloat(shiftResultData?.opening_cash || 0).toFixed(2)"></span></div>
            @if(auth()->user()?->canViewShiftTotals())
            <div class="flex justify-between py-1.5 lg:py-2 border-b"><span class="text-gray-600">Expected in Drawer</span><span class="font-bold" x-text="'₱' + (parseFloat(shiftResultData?.opening_cash || 0) + parseFloat(shiftResultData?.system_sales_total || 0)).toFixed(2)"></span></div>
            @endif
            <div class="flex justify-between py-1.5 lg:py-2 border-b"><span class="text-gray-600">Closing Cash</span><span class="font-bold" x-text="'₱' + parseFloat(shiftResultData?.closing_cash || 0).toFixed(2)"></span></div>
            <div class="flex justify-between py-1.5 lg:py-2" :class="parseFloat(shiftResultData?.cash_variance || 0) >= 0 ? 'text-green-700' : 'text-red-700'">
                <span class="font-bold">Variance</span>
                <span class="font-bold text-base lg:text-lg"
                      x-text="(parseFloat(shiftResultData?.cash_variance || 0) >= 0 ? '+' : '') + '₱' + parseFloat(shiftResultData?.cash_variance || 0).toFixed(2)"></span>
            </div>
        </div>
        <button @click="showShiftResult = false" class="w-full mt-4 lg:mt-5 py-2 lg:py-3 bg-blue-600 text-white rounded-lg text-xs lg:text-base font-medium hover:bg-blue-700">OK</button>
    </div>
</div>

<!-- ITEM DISCOUNT MODAL -->
<div x-show="showItemDiscountModal" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50" x-transition>
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[280px] lg:max-w-xs p-4 lg:p-6" @click.away="showItemDiscountModal = false">
        <h2 class="text-sm lg:text-lg font-bold text-gray-800 mb-0.5 lg:mb-1">Item Discount</h2>
        <p class="text-[11px] lg:text-sm text-gray-500 mb-3 lg:mb-4" x-text="discountEditItem?.product_name || ''"></p>
        <div class="flex gap-1.5 lg:gap-2 mb-2 lg:mb-3">
            <button @click="discountType = 'amount'"
                    class="flex-1 py-1.5 lg:py-2 rounded-lg text-[11px] lg:text-sm font-medium border-2 transition-colors"
                    :class="discountType === 'amount' ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-600'">₱ Amount</button>
            <button @click="discountType = 'percent'"
                    class="flex-1 py-1.5 lg:py-2 rounded-lg text-[11px] lg:text-sm font-medium border-2 transition-colors"
                    :class="discountType === 'percent' ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-600'">% Percent</button>
        </div>
        <input type="number" x-model.number="discountValue" step="0.01" min="0"
               class="w-full p-2 lg:p-3 border-2 rounded-lg text-lg lg:text-xl font-bold text-center focus:border-blue-500 focus:outline-none"
               :placeholder="discountType === 'percent' ? '0%' : '0.00'" @keydown.enter="applyItemDiscount()">
        <div class="grid grid-cols-3 gap-1.5 lg:gap-2 mt-1.5 lg:mt-2">
            <template x-for="pct in [5, 10, 15, 20, 25, 50]" :key="pct">
                <button @click="discountType = 'percent'; discountValue = pct"
                        class="py-1 lg:py-1.5 text-[10px] lg:text-xs font-medium bg-gray-100 rounded hover:bg-gray-200"
                        x-text="pct + '%'"></button>
            </template>
        </div>
        <div class="flex gap-2 lg:gap-3 mt-3 lg:mt-4">
            <button @click="applyItemDiscount()" class="flex-1 py-2 lg:py-3 bg-blue-600 text-white rounded-lg text-xs lg:text-base font-medium hover:bg-blue-700">Apply</button>
            <button @click="discountValue = 0; applyItemDiscount()" class="py-2 lg:py-3 px-3 lg:px-4 bg-gray-200 text-gray-700 rounded-lg text-xs lg:text-base font-medium hover:bg-gray-300">Clear</button>
            <button @click="showItemDiscountModal = false" class="py-2 lg:py-3 px-3 lg:px-4 bg-gray-200 text-gray-700 rounded-lg text-xs lg:text-base font-medium hover:bg-gray-300">Cancel</button>
        </div>
    </div>
</div>

<!-- ORDER-LEVEL DISCOUNT MODAL -->
<div x-show="showOrderDiscountModal" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50" x-transition>
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[280px] lg:max-w-xs p-4 lg:p-6" @click.away="showOrderDiscountModal = false">
        <h2 class="text-sm lg:text-lg font-bold text-gray-800 mb-3 lg:mb-4">Order Discount</h2>
        <div class="flex gap-1.5 lg:gap-2 mb-2 lg:mb-3">
            <button @click="orderDiscountType = 'amount'"
                    class="flex-1 py-1.5 lg:py-2 rounded-lg text-[11px] lg:text-sm font-medium border-2 transition-colors"
                    :class="orderDiscountType === 'amount' ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-600'">₱ Amount</button>
            <button @click="orderDiscountType = 'percent'"
                    class="flex-1 py-1.5 lg:py-2 rounded-lg text-[11px] lg:text-sm font-medium border-2 transition-colors"
                    :class="orderDiscountType === 'percent' ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-600'">% Percent</button>
        </div>
        <input type="number" x-model.number="orderDiscountValue" step="0.01" min="0"
               class="w-full p-2 lg:p-3 border-2 rounded-lg text-lg lg:text-xl font-bold text-center focus:border-blue-500 focus:outline-none"
               :placeholder="orderDiscountType === 'percent' ? '0%' : '0.00'" @keydown.enter="applyOrderDiscount()">
        <div class="grid grid-cols-4 gap-1.5 lg:gap-2 mt-1.5 lg:mt-2">
            <template x-for="pct in [5, 10, 15, 20]" :key="pct">
                <button @click="orderDiscountType = 'percent'; orderDiscountValue = pct"
                        class="py-1 lg:py-1.5 text-[10px] lg:text-xs font-medium bg-gray-100 rounded hover:bg-gray-200"
                        x-text="pct + '%'"></button>
            </template>
        </div>
        <div class="flex gap-2 lg:gap-3 mt-3 lg:mt-4">
            <button @click="applyOrderDiscount()" class="flex-1 py-2 lg:py-3 bg-blue-600 text-white rounded-lg text-xs lg:text-base font-medium hover:bg-blue-700">Apply</button>
            <button @click="orderDiscountValue = 0; applyOrderDiscount()" class="py-2 lg:py-3 px-3 lg:px-4 bg-gray-200 text-gray-700 rounded-lg text-xs lg:text-base font-medium hover:bg-gray-300">Clear</button>
            <button @click="showOrderDiscountModal = false" class="py-2 lg:py-3 px-3 lg:px-4 bg-gray-200 text-gray-700 rounded-lg text-xs lg:text-base font-medium hover:bg-gray-300">Cancel</button>
        </div>
    </div>
</div>

<!-- SUCCESS MODAL -->
<div x-show="showReceipt" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50" @click.self="closeReceipt()">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[340px] lg:max-w-md p-4 lg:p-6 text-center">
        <div class="w-12 h-12 lg:w-16 lg:h-16 mx-auto mb-2 lg:mb-3 rounded-full bg-green-100 flex items-center justify-center">
            <svg class="w-8 h-8 lg:w-10 lg:h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        </div>
        <h2 class="text-lg lg:text-2xl font-bold text-green-700 mb-1 lg:mb-2">Sale Complete!</h2>
        <div class="text-gray-600 mb-3 lg:mb-4">
            <div class="text-xs lg:text-base">Sale #: <span class="font-mono font-bold" x-text="saleDisplayNumber(lastSale)"></span></div>
            <div class="text-xl lg:text-2xl font-bold mt-1 lg:mt-2">Total: <span x-text="'₱' + parseFloat(lastSale?.total || 0).toFixed(2)"></span></div>
            <div x-show="lastSale?.payment_method === 'cash' || paymentMethod === 'cash'" class="text-base lg:text-xl mt-0.5 lg:mt-1">
                Change: <span class="text-green-600 font-bold" x-text="'₱' + parseFloat(lastSale?.change_due || 0).toFixed(2)"></span>
            </div>
            <div x-show="lastSale?.offline && !silentSync" class="mt-2 text-[10px] lg:text-xs text-yellow-600 bg-yellow-50 border border-yellow-200 rounded px-2 lg:px-3 py-1 inline-block">
                Saved offline — will sync when connected
            </div>
        </div>
        <div class="flex gap-2 lg:gap-3 justify-center">
            <template x-if="canUsePrinter()">
                <button @click="buddyPrintReceipt()" class="px-4 py-2 lg:px-6 lg:py-3 bg-green-600 text-white rounded-lg text-xs lg:text-base font-medium hover:bg-green-700 flex items-center gap-1.5 lg:gap-2">
                    <svg class="w-4 h-4 lg:w-5 lg:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Print
                </button>
            </template>
            <button @click="closeReceipt()" class="px-6 py-2 lg:px-8 lg:py-3 bg-blue-600 text-white rounded-lg text-xs lg:text-base font-medium hover:bg-blue-700">
                New Transaction
            </button>
        </div>
    </div>
</div>

<!-- CONFLICT RESOLUTION MODAL -->
<div x-show="showConflictModal" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[400px] lg:max-w-lg p-4 lg:p-6">
        <h2 class="text-base lg:text-xl font-bold text-yellow-700 mb-3 lg:mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 lg:w-6 lg:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
            Sync Conflict
        </h2>
        <div class="space-y-2 mb-4 lg:mb-6 max-h-48 lg:max-h-64 overflow-y-auto">
            <template x-for="c in conflictItems" :key="c.product_id + c.field">
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-2 lg:p-3">
                    <div class="font-medium text-xs lg:text-sm" x-text="c.product_name"></div>
                    <div class="text-[10px] lg:text-xs text-gray-500 mt-1">
                        <span x-text="c.field === 'price' ? 'Price' : c.field"></span>:
                        <span class="text-red-600 line-through" x-text="'₱' + parseFloat(c.local_value).toFixed(2)"></span> &rarr;
                        <span class="text-green-600 font-bold" x-text="'₱' + parseFloat(c.server_value).toFixed(2)"></span>
                    </div>
                </div>
            </template>
        </div>
        <div class="flex gap-2 lg:gap-3">
            <button @click="resolveConflict('server')" class="flex-1 py-2 lg:py-3 bg-green-600 text-white rounded-lg text-xs lg:text-base font-medium hover:bg-green-700">Use Server</button>
            <button @click="resolveConflict('local')" class="flex-1 py-2 lg:py-3 bg-gray-200 text-gray-700 rounded-lg text-xs lg:text-base font-medium hover:bg-gray-300">Keep Local</button>
        </div>
    </div>
</div>

<!-- RECENT TRANSACTIONS MODAL -->
<div x-show="showHistoryModal" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50" x-transition>
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[400px] lg:max-w-lg p-4 lg:p-6" @click.away="showHistoryModal = false">
        <div class="flex items-center justify-between mb-3 lg:mb-4">
            <h2 class="text-base lg:text-xl font-bold text-gray-800">Recent Transactions</h2>
            <button @click="showHistoryModal = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-4 h-4 lg:w-5 lg:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="max-h-60 lg:max-h-80 overflow-y-auto space-y-1.5 lg:space-y-2">
            <template x-for="sale in recentSales" :key="saleRowKey(sale)">
                <div class="bg-gray-50 rounded-lg p-2 lg:p-3 flex items-center justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <div class="font-medium text-[11px] lg:text-sm font-mono">
                            <span x-text="saleDisplayNumber(sale)"></span>
                            <span x-show="!silentSync && (sale.status === 'pending' || sale.sync_status === 'pending')" class="ml-1 text-[9px] lg:text-xs bg-yellow-100 text-yellow-700 px-1 lg:px-1.5 py-0.5 rounded">Offline</span>
                        </div>
                        <div class="text-[10px] lg:text-xs text-gray-400" x-text="new Date(sale.created_at).toLocaleString()"></div>
                        <div class="text-[10px] lg:text-xs text-gray-500 capitalize" x-text="(sale.payment_method || 'cash').replace('_', ' ')"></div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="font-bold text-blue-700 text-xs lg:text-base" x-text="'₱' + parseFloat(sale.total || sale.grand_total || 0).toFixed(2)"></div>
                        <div class="text-[10px] lg:text-xs" :class="sale.status === 'completed' || sale.status === 'synced' ? 'text-green-600' : 'text-yellow-600'"
                             x-text="sale.status === 'completed' || sale.status === 'synced' ? 'Synced' : (sale.status || 'Pending')"></div>
                    </div>
                    <button type="button"
                            @click.stop="reprintSale(sale)"
                            :disabled="!canUsePrinter() || reprintingSaleKey === saleRowKey(sale)"
                            class="shrink-0 min-h-[40px] min-w-[72px] px-2.5 py-2 rounded-lg text-[10px] lg:text-xs font-semibold flex items-center justify-center gap-1 transition-colors"
                            :class="canUsePrinter() ? 'bg-green-600 text-white hover:bg-green-700 active:bg-green-800' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                            :title="canUsePrinter() ? 'Reprint receipt' : 'Printer requires INSABuddy or Android app'">
                        <svg x-show="reprintingSaleKey !== saleRowKey(sale)" class="w-3.5 h-3.5 lg:w-4 lg:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        <svg x-show="reprintingSaleKey === saleRowKey(sale)" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <span x-text="reprintingSaleKey === saleRowKey(sale) ? '...' : 'Reprint'"></span>
                    </button>
                </div>
            </template>
            <div x-show="recentSales.length === 0" class="text-center py-6 lg:py-8 text-gray-400 text-xs lg:text-sm">No recent transactions.</div>
        </div>
    </div>
</div>

<!-- X/Z READING RESULT MODAL -->
<div x-show="showReadingModal" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50" x-transition>
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[340px] lg:max-w-md p-4 lg:p-6">
        <h2 class="text-base lg:text-xl font-bold mb-1 flex items-center gap-2"
            :class="readingData?.type === 'z' ? 'text-orange-700' : 'text-blue-700'">
            <svg class="w-5 h-5 lg:w-6 lg:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            <span x-text="readingData?.type === 'z' ? 'Z-Reading #' + readingData.z_count : 'X-Reading'"></span>
        </h2>
        <p class="text-[10px] lg:text-xs text-gray-500 mb-3 lg:mb-4" x-text="readingData?.generated_at"></p>

        <div class="space-y-1.5 lg:space-y-2 text-[11px] lg:text-sm">
            @if(auth()->user()?->canViewShiftTotals())
            <div class="flex justify-between py-1.5 lg:py-2 border-b"><span class="text-gray-600">Total Sales</span><span class="font-bold text-sm lg:text-lg" x-text="'₱' + parseFloat(readingData?.total_sales || 0).toFixed(2)"></span></div>
            @endif
            <div class="flex justify-between py-1 border-b"><span class="text-gray-600">Transactions</span><span class="font-semibold" x-text="readingData?.transaction_count || 0"></span></div>
            <div class="flex justify-between py-1 border-b"><span class="text-gray-600">Discounts</span><span class="font-semibold" x-text="'₱' + parseFloat(readingData?.discount_total || 0).toFixed(2)"></span></div>
            <div class="flex justify-between py-1 border-b"><span class="text-gray-600">Voids</span><span class="font-semibold" x-text="'₱' + parseFloat(readingData?.void_total || 0).toFixed(2)"></span></div>
        </div>

        <template x-if="readingData?.payment_breakdown">
            <div class="mt-3 lg:mt-4">
                <div class="text-[9px] lg:text-xs font-bold text-gray-500 uppercase mb-1.5 lg:mb-2">Payment Breakdown</div>
                <template x-for="[method, amount] in Object.entries(readingData.payment_breakdown || {})" :key="method">
                    <template x-if="amount > 0">
                        <div class="flex justify-between py-0.5 lg:py-1 px-1.5 lg:px-2 rounded text-[11px] lg:text-sm" :class="method === 'cash' ? 'bg-green-50' : 'bg-gray-50'">
                            <span class="text-gray-600 capitalize" x-text="method.replace('_', ' ')"></span>
                            <span class="font-medium" x-text="'₱' + parseFloat(amount).toFixed(2)"></span>
                        </div>
                    </template>
                </template>
            </div>
        </template>

        <template x-if="readingData?.type === 'z'">
            <div class="mt-2 lg:mt-3 p-1.5 lg:p-2 bg-orange-50 border border-orange-200 rounded text-[10px] lg:text-xs text-orange-700">
                Totals have been reset. Z-Count: <strong x-text="readingData.z_count"></strong>
            </div>
        </template>

        <div class="mt-4 lg:mt-5 flex gap-2 lg:gap-3">
            <button @click="printReading()" x-show="buddyConnected" class="flex-1 py-2 lg:py-3 bg-blue-600 text-white rounded-lg text-xs lg:text-base font-medium hover:bg-blue-700">Print</button>
            <button @click="showReadingModal = false" class="flex-1 py-2 lg:py-3 bg-gray-200 text-gray-700 rounded-lg text-xs lg:text-base font-medium hover:bg-gray-300">Close</button>
        </div>
    </div>
</div>

<!-- Z-READING CONFIRMATION MODAL -->
<div x-show="showZConfirm" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50" x-transition>
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[300px] lg:max-w-sm p-4 lg:p-6 text-center">
        <div class="w-12 h-12 lg:w-16 lg:h-16 mx-auto mb-2 lg:mb-3 rounded-full bg-orange-100 flex items-center justify-center">
            <svg class="w-8 h-8 lg:w-10 lg:h-10 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
        </div>
        <h2 class="text-base lg:text-xl font-bold text-orange-700 mb-1.5 lg:mb-2">Generate Z-Reading?</h2>
        <p class="text-[11px] lg:text-sm text-gray-600 mb-4 lg:mb-5">This is an <strong>end-of-day reading</strong> that will <strong>RESET</strong> totals. This cannot be undone.</p>
        <div class="flex gap-2 lg:gap-3">
            <button @click="doGenerateZReading()" class="flex-1 py-2 lg:py-3 bg-orange-600 text-white rounded-lg text-xs lg:text-base font-medium hover:bg-orange-700">Proceed</button>
            <button @click="showZConfirm = false" class="flex-1 py-2 lg:py-3 bg-gray-200 text-gray-700 rounded-lg text-xs lg:text-base font-medium hover:bg-gray-300">Cancel</button>
        </div>
    </div>
</div>

<!-- PRINTER SETTINGS MODAL -->
<div x-show="showPrinterModal" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50" x-transition>
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[360px] lg:max-w-md p-4 lg:p-6" @click.away="showPrinterModal = false">
        <div class="flex items-center justify-between mb-3 lg:mb-4">
            <h2 class="text-base lg:text-xl font-bold text-gray-800 flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Printer Settings
            </h2>
            <button @click="showPrinterModal = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-4 h-4 lg:w-5 lg:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <!-- Step indicators -->
        <div class="flex items-center gap-1 mb-4 lg:mb-5">
            <template x-for="(label, idx) in ['Scan', 'Test', 'Save']" :key="idx">
                <div class="flex items-center flex-1">
                    <div class="flex items-center gap-1.5 flex-1">
                        <span class="w-6 h-6 lg:w-7 lg:h-7 rounded-full flex items-center justify-center text-[10px] lg:text-xs font-bold shrink-0"
                              :class="printerStep > idx + 1 ? 'bg-green-500 text-white' : (printerStep === idx + 1 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500')"
                              x-text="idx + 1"></span>
                        <span class="text-[10px] lg:text-xs font-medium truncate"
                              :class="printerStep === idx + 1 ? 'text-blue-700' : 'text-gray-400'"
                              x-text="label"></span>
                    </div>
                    <div x-show="idx < 2" class="w-3 lg:w-4 h-0.5 mx-0.5 shrink-0"
                         :class="printerStep > idx + 1 ? 'bg-green-400' : 'bg-gray-200'"></div>
                </div>
            </template>
        </div>

        <p class="text-[10px] lg:text-xs text-gray-500 mb-3 lg:mb-4" x-text="printerStatusMessage"></p>

        <!-- Paper & font layout -->
        <div class="bg-gray-50 border rounded-lg p-3 lg:p-4 mb-3 lg:mb-4 space-y-3">
            <div class="text-[9px] lg:text-xs font-bold text-gray-500 uppercase">Receipt Layout</div>
            <div>
                <label class="block text-[11px] lg:text-sm font-medium text-gray-600 mb-1">Paper Size</label>
                <select x-model="printerPaperSize" @change="savePrinterLayoutSettings()"
                        class="w-full p-2 lg:p-3 border rounded-lg text-xs lg:text-sm focus:border-blue-500 focus:outline-none">
                    <option value="57mm">57mm (58mm thermal)</option>
                    <option value="87mm">87mm (80mm thermal)</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] lg:text-sm font-medium text-gray-600 mb-1">Font Mode</label>
                <select x-model="printerFontMode" @change="savePrinterLayoutSettings()"
                        class="w-full p-2 lg:p-3 border rounded-lg text-xs lg:text-sm focus:border-blue-500 focus:outline-none">
                    <option value="paper_size">Paper Size (standard)</option>
                    <option value="fine_print">Fine Print (condensed)</option>
                </select>
            </div>
            <p class="text-[10px] lg:text-xs text-gray-400" x-text="printerLayoutHint"></p>
        </div>

        <!-- Step 1: Scan -->
        <div x-show="printerStep === 1" class="space-y-3">
            <p class="text-[11px] lg:text-sm text-gray-600">Search for Bluetooth, USB, or built-in thermal printers on this device.</p>
            <button @click="scanPrinters()" :disabled="printerScanning"
                    class="w-full py-2.5 lg:py-3 bg-blue-600 text-white rounded-lg text-xs lg:text-base font-medium hover:bg-blue-700 disabled:bg-blue-400 disabled:cursor-wait flex items-center justify-center gap-2">
                <svg x-show="printerScanning" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="printerScanning ? 'Scanning...' : 'Scan for Printers'"></span>
            </button>
            <div x-show="printerList.length > 0">
                <label class="block text-[11px] lg:text-sm font-medium text-gray-600 mb-1">Found printers</label>
                <select x-model="printerSelectedIndex" class="w-full p-2 lg:p-3 border rounded-lg text-xs lg:text-sm focus:border-blue-500 focus:outline-none">
                    <option value="-1" disabled selected>Select a printer...</option>
                    <template x-for="(p, i) in printerList" :key="p.type + '-' + p.name">
                        <option :value="i" x-text="'[' + p.type + '] ' + p.name"></option>
                    </template>
                </select>
                <button @click="printerStep = 2" :disabled="printerSelectedIndex < 0"
                        class="w-full mt-2 py-2 lg:py-2.5 bg-gray-800 text-white rounded-lg text-xs lg:text-sm font-medium hover:bg-gray-900 disabled:bg-gray-300 disabled:cursor-not-allowed">
                    Continue to Test Print
                </button>
            </div>
        </div>

        <!-- Step 2: Select & Test -->
        <div x-show="printerStep === 2" class="space-y-3">
            <label class="block text-[11px] lg:text-sm font-medium text-gray-600 mb-1">Selected printer</label>
            <select x-model="printerSelectedIndex" class="w-full p-2 lg:p-3 border rounded-lg text-xs lg:text-sm focus:border-blue-500 focus:outline-none">
                <option value="-1" disabled>Select a printer...</option>
                <template x-for="(p, i) in printerList" :key="p.type + '-' + p.name">
                    <option :value="i" x-text="'[' + p.type + '] ' + p.name"></option>
                </template>
            </select>
            <button @click="testSelectedPrinter()" :disabled="printerSelectedIndex < 0 || printerTesting"
                    class="w-full py-2.5 lg:py-3 bg-green-600 text-white rounded-lg text-xs lg:text-base font-medium hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                <svg x-show="printerTesting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="printerTesting ? 'Printing...' : 'Test Print'"></span>
            </button>
            <button @click="printerStep = 1" class="w-full py-2 text-[11px] lg:text-sm text-gray-500 hover:text-gray-700">← Back to Scan</button>
        </div>

        <!-- Step 3: Save default -->
        <div x-show="printerStep === 3" class="space-y-3">
            <div class="bg-gray-50 border rounded-lg p-3 lg:p-4">
                <div class="text-[9px] lg:text-xs font-bold text-gray-500 uppercase mb-2">Current Default Printer</div>
                <template x-if="printerDefault.connected && printerDefault.name">
                    <div>
                        <div class="font-bold text-sm lg:text-base text-green-700" x-text="printerDefault.name"></div>
                        <div class="text-[10px] lg:text-xs text-gray-500 capitalize" x-text="printerDefault.type + ' · Connected'"></div>
                    </div>
                </template>
                <template x-if="!printerDefault.connected || !printerDefault.name">
                    <div class="text-[11px] lg:text-sm text-gray-400">No default printer saved</div>
                </template>
            </div>
            <p class="text-[11px] lg:text-sm text-gray-600">Save the tested printer as your default for receipts and drawer commands.</p>
            <button @click="saveDefaultPrinter()" :disabled="printerSelectedIndex < 0 || printerSaving"
                    class="w-full py-2.5 lg:py-3 bg-blue-600 text-white rounded-lg text-xs lg:text-base font-medium hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                <svg x-show="printerSaving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="printerSaving ? 'Saving...' : 'Set as Default & Save'"></span>
            </button>
            <button @click="printerStep = 2" class="w-full py-2 text-[11px] lg:text-sm text-gray-500 hover:text-gray-700">← Back to Test Print</button>
        </div>

        <div class="mt-4 pt-3 border-t">
            <button @click="showPrinterModal = false" class="w-full py-2 lg:py-2.5 bg-gray-200 text-gray-700 rounded-lg text-xs lg:text-base font-medium hover:bg-gray-300">Close</button>
        </div>
    </div>
</div>

<!-- I/O SETTINGS MODAL -->
<div x-show="showIoModal" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50" x-transition>
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[360px] lg:max-w-md p-4 lg:p-6 max-h-[90vh] overflow-y-auto" @click.away="showIoModal = false">
        <div class="flex items-center justify-between mb-3 lg:mb-4">
            <h2 class="text-base lg:text-xl font-bold text-gray-800 flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                I/O Settings
            </h2>
            <button @click="showIoModal = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-4 h-4 lg:w-5 lg:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <!-- Option picker -->
        <div x-show="ioMenuView" class="space-y-2">
            <p class="text-[11px] lg:text-sm text-gray-600 mb-2">Configure input devices for this terminal.</p>
            <div x-show="!ioApiAvailable && (buddyConnected || hasNativeBridge)" class="text-[10px] lg:text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2 mb-2">
                Full device setup requires INSAPOS v3. INSABuddy supports HID scanner test only.
            </div>
            <button @click="startIoWizard('keyboard')" class="w-full text-left p-3 border rounded-lg hover:bg-gray-50 transition-colors">
                <div class="font-semibold text-sm text-gray-800">Keyboard / Mouse</div>
                <div class="text-[10px] lg:text-xs text-gray-500">Scan, test, and set default keyboard or mouse</div>
            </button>
            <button @click="startIoWizard('scanner')" class="w-full text-left p-3 border rounded-lg hover:bg-gray-50 transition-colors">
                <div class="font-semibold text-sm text-gray-800">QR / Barcode Scanner</div>
                <div class="text-[10px] lg:text-xs text-gray-500">USB or Bluetooth HID barcode scanner</div>
            </button>
            <button @click="startIoWizard('camera')" class="w-full text-left p-3 border rounded-lg hover:bg-gray-50 transition-colors">
                <div class="font-semibold text-sm text-gray-800">Camera On or Off</div>
                <div class="text-[10px] lg:text-xs text-gray-500">Allow camera fallback when scanning products</div>
            </button>
        </div>

        <!-- Wizard -->
        <div x-show="!ioMenuView">
            <div class="flex items-center gap-1 mb-3">
                <button @click="ioBackToMenu()" class="text-[10px] lg:text-xs text-blue-600 hover:underline">← All options</button>
                <span class="text-[10px] lg:text-xs text-gray-400 ml-auto" x-text="ioWizardTitle"></span>
            </div>

            <div class="flex items-center gap-1 mb-4 lg:mb-5" x-show="ioOption !== 'camera'">
                <template x-for="(label, idx) in ['Scan', 'Test', 'Save']" :key="idx">
                    <div class="flex items-center flex-1">
                        <div class="flex items-center gap-1.5 flex-1">
                            <span class="w-6 h-6 lg:w-7 lg:h-7 rounded-full flex items-center justify-center text-[10px] lg:text-xs font-bold shrink-0"
                                  :class="ioStep > idx + 1 ? 'bg-green-500 text-white' : (ioStep === idx + 1 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500')"
                                  x-text="idx + 1"></span>
                            <span class="text-[10px] lg:text-xs font-medium truncate"
                                  :class="ioStep === idx + 1 ? 'text-blue-700' : 'text-gray-400'"
                                  x-text="label"></span>
                        </div>
                        <div x-show="idx < 2" class="w-3 lg:w-4 h-0.5 mx-0.5 shrink-0"
                             :class="ioStep > idx + 1 ? 'bg-green-400' : 'bg-gray-200'"></div>
                    </div>
                </template>
            </div>

            <p class="text-[10px] lg:text-xs text-gray-500 mb-3 lg:mb-4" x-text="ioStatusMessage"></p>

            <!-- Keyboard / Mouse wizard -->
            <div x-show="ioOption === 'keyboard'">
                    <div x-show="ioStep === 1" class="space-y-3">
                        <p class="text-[11px] lg:text-sm text-gray-600">Search for USB or Bluetooth keyboards and mice.</p>
                        <button @click="scanIoDevices()" :disabled="ioScanning"
                                class="w-full py-2.5 lg:py-3 bg-blue-600 text-white rounded-lg text-xs lg:text-base font-medium hover:bg-blue-700 disabled:bg-blue-400 flex items-center justify-center gap-2">
                            <svg x-show="ioScanning" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            <span x-text="ioScanning ? 'Scanning...' : 'Scan Devices'"></span>
                        </button>
                        <div x-show="ioKeyboards.length > 0 || ioMice.length > 0" class="space-y-2">
                            <div x-show="ioKeyboards.length > 0">
                                <label class="block text-[11px] lg:text-sm font-medium text-gray-600 mb-1">Keyboard</label>
                                <select x-model="ioSelectedKeyboardIndex" class="w-full p-2 lg:p-3 border rounded-lg text-xs lg:text-sm">
                                    <option value="-1">None</option>
                                    <template x-for="(d, i) in ioKeyboards" :key="'kb-'+d.id">
                                        <option :value="i" x-text="d.name"></option>
                                    </template>
                                </select>
                            </div>
                            <div x-show="ioMice.length > 0">
                                <label class="block text-[11px] lg:text-sm font-medium text-gray-600 mb-1">Mouse</label>
                                <select x-model="ioSelectedMouseIndex" class="w-full p-2 lg:p-3 border rounded-lg text-xs lg:text-sm">
                                    <option value="-1">None</option>
                                    <template x-for="(d, i) in ioMice" :key="'ms-'+d.id">
                                        <option :value="i" x-text="d.name"></option>
                                    </template>
                                </select>
                            </div>
                            <button @click="ioStep = 2" :disabled="ioSelectedKeyboardIndex < 0 && ioSelectedMouseIndex < 0"
                                    class="w-full py-2 lg:py-2.5 bg-gray-800 text-white rounded-lg text-xs lg:text-sm font-medium disabled:bg-gray-300">Continue to Test</button>
                        </div>
                    </div>
                    <div x-show="ioStep === 2" class="space-y-3">
                        <p class="text-[11px] lg:text-sm text-gray-600">Type on the keyboard or move the mouse, then run test.</p>
                        <button @click="testIo()" :disabled="ioTesting"
                                class="w-full py-2.5 lg:py-3 bg-green-600 text-white rounded-lg text-xs lg:text-base font-medium disabled:bg-gray-300 flex items-center justify-center gap-2">
                            <span x-text="ioTesting ? 'Testing...' : 'Test Input'"></span>
                        </button>
                        <button @click="ioStep = 3" class="w-full py-2 lg:py-2.5 bg-gray-800 text-white rounded-lg text-xs lg:text-sm font-medium">Continue to Save</button>
                        <button @click="ioStep = 1" class="w-full py-2 text-[11px] lg:text-sm text-gray-500">← Back to Scan</button>
                    </div>
                    <div x-show="ioStep === 3" class="space-y-3">
                        <button @click="saveIoDefault()" :disabled="ioSaving"
                                class="w-full py-2.5 lg:py-3 bg-blue-600 text-white rounded-lg text-xs lg:text-base font-medium disabled:bg-gray-300 flex items-center justify-center gap-2">
                            <span x-text="ioSaving ? 'Saving...' : 'Set as Default & Save'"></span>
                        </button>
                        <button @click="ioStep = 2" class="w-full py-2 text-[11px] lg:text-sm text-gray-500">← Back to Test</button>
                    </div>
            </div>

            <!-- Barcode scanner wizard -->
            <div x-show="ioOption === 'scanner'">
                    <div x-show="ioStep === 1" class="space-y-3">
                        <p class="text-[11px] lg:text-sm text-gray-600">Search for HID barcode scanners connected to this device.</p>
                        <button @click="scanIoDevices()" :disabled="ioScanning"
                                class="w-full py-2.5 lg:py-3 bg-blue-600 text-white rounded-lg text-xs lg:text-base font-medium hover:bg-blue-700 disabled:bg-blue-400 flex items-center justify-center gap-2">
                            <span x-text="ioScanning ? 'Scanning...' : 'Scan for Scanners'"></span>
                        </button>
                        <div x-show="ioScanners.length > 0">
                            <label class="block text-[11px] lg:text-sm font-medium text-gray-600 mb-1">Found scanners</label>
                            <select x-model="ioSelectedScannerIndex" class="w-full p-2 lg:p-3 border rounded-lg text-xs lg:text-sm">
                                <option value="-1" disabled>Select a scanner...</option>
                                <template x-for="(d, i) in ioScanners" :key="'sc-'+d.id">
                                    <option :value="i" x-text="d.name"></option>
                                </template>
                            </select>
                            <button @click="ioStep = 2" :disabled="ioSelectedScannerIndex < 0"
                                    class="w-full mt-2 py-2 lg:py-2.5 bg-gray-800 text-white rounded-lg text-xs lg:text-sm font-medium disabled:bg-gray-300">Continue to Test</button>
                        </div>
                    </div>
                    <div x-show="ioStep === 2" class="space-y-3">
                        <p class="text-[11px] lg:text-sm text-gray-600">Scan a barcode with your device, then tap test.</p>
                        <button @click="testIo()" :disabled="ioTesting"
                                class="w-full py-2.5 lg:py-3 bg-green-600 text-white rounded-lg text-xs lg:text-base font-medium disabled:bg-gray-300">
                            <span x-text="ioTesting ? 'Checking...' : 'Test Scanner'"></span>
                        </button>
                        <button @click="ioStep = 3" class="w-full py-2 lg:py-2.5 bg-gray-800 text-white rounded-lg text-xs lg:text-sm font-medium">Continue to Save</button>
                        <button @click="ioStep = 1" class="w-full py-2 text-[11px] lg:text-sm text-gray-500">← Back to Scan</button>
                    </div>
                    <div x-show="ioStep === 3" class="space-y-3">
                        <button @click="saveIoDefault()" :disabled="ioSaving || ioSelectedScannerIndex < 0"
                                class="w-full py-2.5 lg:py-3 bg-blue-600 text-white rounded-lg text-xs lg:text-base font-medium disabled:bg-gray-300">
                            <span x-text="ioSaving ? 'Saving...' : 'Set as Default & Save'"></span>
                        </button>
                        <button @click="ioStep = 2" class="w-full py-2 text-[11px] lg:text-sm text-gray-500">← Back to Test</button>
                    </div>
            </div>

            <!-- Camera toggle -->
            <div x-show="ioOption === 'camera'" class="space-y-4">
                    <p class="text-[11px] lg:text-sm text-gray-600">When off, product scan uses the physical HID scanner only. When on, the app can fall back to the device camera (ZXing).</p>
                    <label class="flex items-center justify-between p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                        <span class="text-sm font-medium text-gray-800">Use camera for scans</span>
                        <input type="checkbox" x-model="useCameraForScan" class="w-5 h-5 rounded text-blue-600">
                    </label>
                    <button @click="saveIoDefault()" :disabled="ioSaving"
                            class="w-full py-2.5 lg:py-3 bg-blue-600 text-white rounded-lg text-xs lg:text-base font-medium disabled:bg-gray-300">
                        <span x-text="ioSaving ? 'Saving...' : 'Save Preference'"></span>
                    </button>
            </div>
        </div>

        <div class="mt-4 pt-3 border-t">
            <button @click="showIoModal = false" class="w-full py-2 lg:py-2.5 bg-gray-200 text-gray-700 rounded-lg text-xs lg:text-base font-medium hover:bg-gray-300">Close</button>
        </div>
    </div>
</div>

<!-- POS SETTINGS MODAL -->
<div x-show="showPosSettingsModal" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50 p-2 sm:p-4" x-transition>
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[380px] sm:max-w-xl lg:max-w-2xl p-4 lg:p-6 max-h-[90vh] overflow-y-auto" @click.away="showPosSettingsModal = false">
        <div class="flex items-center justify-between mb-3 lg:mb-4">
            <h2 class="text-base lg:text-xl font-bold text-gray-800 flex items-center gap-2">
                <svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                POS Settings
            </h2>
            <button @click="showPosSettingsModal = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-4 h-4 lg:w-5 lg:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <div x-show="posSettingsLoading" class="text-center py-6 text-sm text-gray-500">Loading settings…</div>

        <div x-show="!posSettingsLoading" class="space-y-4">
            <!-- Network -->
            <div class="rounded-xl border p-3" :class="posSettings.network_online ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50'">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Network</div>
                <div class="text-sm font-medium" :class="posSettings.network_online ? 'text-green-800' : 'text-amber-800'"
                     x-text="posSettings.network_online ? 'Online — sync available' : 'Offline — using cached data'"></div>
            </div>

            <!-- Customer Display -->
            <div class="rounded-xl border border-green-200 bg-green-50/50 p-3 space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-green-900">Customer Display</div>
                        <div class="text-[10px] lg:text-xs text-green-700 truncate"
                             x-text="posSettings.customer_display_available ? ('Secondary screen: ' + (posSettings.customer_display_name || 'Detected')) : 'No secondary display detected on this device'"></div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0" x-show="config.canEditCdSettings">
                        <input type="checkbox" x-model="posSettings.customer_display_enabled" @change="saveCustomerDisplayEnabled()" class="sr-only peer">
                        <div class="w-9 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:bg-green-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                </div>

                <template x-if="config.canEditCdSettings">
                    <div class="space-y-3 border-t border-green-200/80 pt-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] text-gray-500 mb-1">Layout orientation</label>
                                <select x-model="posSettings.customer_display_orientation" class="w-full p-2 border rounded-lg text-xs bg-white">
                                    <option value="auto">Auto</option>
                                    <option value="portrait">Portrait</option>
                                    <option value="landscape">Landscape</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] text-gray-500 mb-1">Media rotation</label>
                                <select x-model="posSettings.customer_display_rotation_mode" class="w-full p-2 border rounded-lg text-xs bg-white">
                                    <option value="mix">Mix (photos + videos)</option>
                                    <option value="loop_photos">Loop photos</option>
                                    <option value="loop_videos">Loop videos</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-500 mb-1">Cart visibility</label>
                            <select x-model="posSettings.customer_display_show_cart" class="w-full p-2 border rounded-lg text-xs bg-white">
                                <option value="1">Show cart</option>
                                <option value="0">Hide cart</option>
                            </select>
                        </div>

                        <!-- Photo upload -->
                        <div class="rounded-lg border border-green-100 bg-white p-2 space-y-2">
                            <div class="text-xs font-medium text-gray-800">Promo photo</div>
                            <div x-show="posSettings.customer_display_photo" class="flex items-center gap-2">
                                <img :src="posSettings.customer_display_photo" alt="Current photo" class="w-14 h-14 object-cover rounded border bg-gray-100"
                                     x-on:error="$el.style.display='none'">
                                <div class="min-w-0 flex-1">
                                    <div class="text-[10px] text-green-700 truncate" x-text="cdMediaFilename(posSettings.customer_display_photo)"></div>
                                    <div class="text-[10px] text-gray-400">JPG or PNG, max 5 MB</div>
                                </div>
                            </div>
                            <div x-show="!posSettings.customer_display_photo" class="text-[10px] text-gray-400">No photo uploaded yet</div>
                            <div class="flex gap-2 items-center">
                                <input type="file" accept="image/jpeg,image/png,.jpg,.jpeg,.png" @change="onCdPhotoSelected($event)"
                                       class="flex-1 text-[10px] file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-[10px] file:bg-gray-100 file:text-gray-700">
                                <button type="button" @click="uploadCdPhoto()" :disabled="!cdPhotoFile || cdPhotoUploading"
                                        class="px-3 py-1.5 text-xs font-medium bg-gray-800 text-white rounded-lg hover:bg-gray-900 disabled:bg-gray-300">
                                    <span x-text="cdPhotoUploading ? 'Uploading…' : 'Upload'"></span>
                                </button>
                            </div>
                            <img x-show="cdPhotoPreview" :src="cdPhotoPreview" alt="Preview" class="w-full max-h-24 object-contain rounded border bg-gray-50">
                        </div>

                        <!-- Video upload -->
                        <div class="rounded-lg border border-green-100 bg-white p-2 space-y-2">
                            <div class="text-xs font-medium text-gray-800">Promo video</div>
                            <div x-show="posSettings.customer_display_video">
                                <div class="text-[10px] text-green-700 truncate" x-text="cdMediaFilename(posSettings.customer_display_video)"></div>
                                <div class="text-[10px] text-gray-400">MP4, max 50 MB</div>
                            </div>
                            <div x-show="!posSettings.customer_display_video" class="text-[10px] text-gray-400">No video uploaded yet</div>
                            <div class="flex gap-2 items-center">
                                <input type="file" accept="video/mp4,.mp4" @change="onCdVideoSelected($event)"
                                       class="flex-1 text-[10px] file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-[10px] file:bg-gray-100 file:text-gray-700">
                                <button type="button" @click="uploadCdVideo()" :disabled="!cdVideoFile || cdVideoUploading"
                                        class="px-3 py-1.5 text-xs font-medium bg-gray-800 text-white rounded-lg hover:bg-gray-900 disabled:bg-gray-300">
                                    <span x-text="cdVideoUploading ? 'Uploading…' : 'Upload'"></span>
                                </button>
                            </div>
                        </div>

                        <button type="button" @click="saveCustomerDisplaySettings()" :disabled="cdSaving"
                                class="w-full py-2 text-xs font-medium bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:bg-gray-300">
                            <span x-text="cdSaving ? 'Saving…' : 'Save Customer Display Settings'"></span>
                        </button>
                    </div>
                </template>
                <p x-show="!config.canEditCdSettings" class="text-[10px] text-gray-500">Contact an admin to change customer display settings.</p>

                <div class="flex gap-2" x-show="hasNativeBridge && posSettings.customer_display_available">
                    <button @click="testCustomerDisplay()" class="flex-1 py-2 text-xs font-medium bg-green-600 text-white rounded-lg hover:bg-green-700">Test Display</button>
                    <button @click="syncCustomerDisplayCart()" class="flex-1 py-2 text-xs font-medium bg-white border border-green-300 text-green-800 rounded-lg hover:bg-green-50">Mirror Cart</button>
                </div>
                <p x-show="!hasNativeBridge" class="text-[10px] text-gray-500">Customer display preview requires the INSAPOS Android app on dual-screen hardware.</p>
            </div>

            <!-- Printer -->
            <div class="rounded-xl border p-3 space-y-2">
                <div class="text-sm font-semibold text-gray-800">Printer</div>
                <div class="flex items-center justify-between py-1">
                    <div>
                        <div class="text-xs text-gray-700">Auto-print receipt</div>
                        <div class="text-[10px] text-gray-400">Print after each completed sale</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                        <input type="checkbox" x-model="autoPrintReceipt" @change="saveAutoPrintSetting()" class="sr-only peer">
                        <div class="w-9 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:bg-green-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[10px] text-gray-500 mb-1">Paper size</label>
                        <select x-model="posSettings.paper_size" @change="savePosPaperSettings()" class="w-full p-2 border rounded-lg text-xs">
                            <option value="57mm">57mm</option>
                            <option value="87mm">87mm</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-500 mb-1">Font mode</label>
                        <select x-model="posSettings.font_mode" @change="savePosPaperSettings()" class="w-full p-2 border rounded-lg text-xs">
                            <option value="paper_size">Standard</option>
                            <option value="fine_print">Fine print</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-2" x-show="buddyConnected || hasNativeBridge">
                    <button @click="showPosSettingsModal = false; openPrinterSettings()"
                            class="flex-1 py-2 text-xs font-medium bg-gray-100 text-gray-800 rounded-lg hover:bg-gray-200">Printer Setup</button>
                    <button @click="settingsTestPrint()" :disabled="settingsTestPrinting"
                            class="flex-1 py-2 text-xs font-medium bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:bg-gray-300">
                        <span x-text="settingsTestPrinting ? 'Printing…' : 'Test Print'"></span>
                    </button>
                </div>
            </div>

            <!-- Sync -->
            <div class="rounded-xl border p-3 space-y-2">
                <div class="text-sm font-semibold text-gray-800">Sync</div>
                <div class="text-[10px] lg:text-xs text-gray-500 space-y-0.5">
                    <div x-show="posSettings.last_sync_at">
                        Last sync: <span class="font-medium text-gray-700" x-text="posSettings.last_sync_at || '—'"></span>
                    </div>
                    <div x-show="posSettings.products_cached > 0">
                        Catalog: <span class="font-medium text-gray-700" x-text="posSettings.products_cached + ' products cached'"></span>
                    </div>
                </div>
                <button @click="settingsManualSync()" class="w-full py-2 text-xs font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700">Sync Now</button>
            </div>

            <!-- Hardware -->
            <div class="rounded-xl border p-3 space-y-2" x-show="hasNativeBridge || buddyConnected">
                <div class="text-sm font-semibold text-gray-800">Hardware</div>
                <div class="text-[10px] lg:text-xs text-gray-600" x-text="posSettings.hardware_summary || 'Tap scan to detect USB devices'"></div>
                <div class="flex gap-2">
                    <button @click="settingsScanHardware()" class="flex-1 py-2 text-xs font-medium bg-gray-800 text-white rounded-lg hover:bg-gray-900">Scan Devices</button>
                    <button @click="showPosSettingsModal = false; openIoSettings()" class="flex-1 py-2 text-xs font-medium bg-gray-100 text-gray-800 rounded-lg hover:bg-gray-200">I/O Setup</button>
                </div>
            </div>

            <!-- Display -->
            <div class="rounded-xl border border-green-200 bg-green-50/40 p-3 space-y-2" x-show="hasNativeBridge">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-semibold text-green-900">Display</div>
                        <div class="text-[10px] lg:text-xs text-green-700">Allow minimize &amp; show system navigation bars</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" x-model="posSettings.allow_minimize" @change="saveAllowMinimizeSetting()" class="sr-only peer">
                        <div class="w-9 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:bg-green-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                </div>
            </div>

            <!-- Shift -->
            <div class="rounded-xl border p-3 space-y-2" x-show="canManageShift()">
                <div class="text-sm font-semibold text-gray-800">Shift</div>
                <div class="text-[10px] lg:text-xs text-gray-600"
                     x-text="activeShift ? ('Active since ' + new Date(activeShift.opened_at).toLocaleString()) : 'No active shift'"></div>
                <div class="flex gap-2">
                    <button x-show="!activeShift" @click="showPosSettingsModal = false; showShiftOpenModal = true"
                            class="flex-1 py-2 text-xs font-medium bg-green-600 text-white rounded-lg hover:bg-green-700">Open Shift</button>
                    <button x-show="activeShift" @click="showPosSettingsModal = false; showShiftCloseModal = true"
                            class="flex-1 py-2 text-xs font-medium bg-red-600 text-white rounded-lg hover:bg-red-700">Close Shift</button>
                </div>
            </div>

            <!-- About -->
            <div class="rounded-xl border p-3">
                <div class="text-sm font-semibold text-gray-800 mb-1">About</div>
                <div class="text-[10px] lg:text-xs text-gray-600 space-y-0.5">
                    <div x-text="'App: INSA POS v' + (posSettings.app_version || '—')"></div>
                    <div x-text="'Device: ' + (posSettings.device_model || '—')"></div>
                </div>
            </div>
        </div>

        <div class="mt-4 pt-3 border-t">
            <button @click="showPosSettingsModal = false" class="w-full py-2 lg:py-2.5 bg-gray-200 text-gray-700 rounded-lg text-xs lg:text-base font-medium hover:bg-gray-300">Close</button>
        </div>
    </div>
</div>

<!-- CUSTOMER REWARDS / DIY BIZ REWARDS SCAN MODAL -->
<div x-show="showRewardsModal" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50" x-transition>
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[340px] lg:max-w-sm p-4 lg:p-6" @click.away="showRewardsModal = false">
        <h2 class="text-sm lg:text-lg font-bold text-purple-700 mb-1 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path></svg>
            DIY Biz Rewards
        </h2>
        <p class="text-[10px] lg:text-xs text-gray-500 mb-3">Scan customer QR/barcode or enter card number to tag this sale.</p>

        <div x-show="selectedCustomer" class="mb-3 bg-green-50 border border-green-200 rounded-lg p-2 lg:p-3">
            <div class="flex items-center justify-between">
                <div>
                    <div class="font-bold text-xs lg:text-sm text-green-800" x-text="selectedCustomer?.name"></div>
                    <div class="text-[10px] lg:text-xs text-green-600" x-text="selectedCustomer?.phone || selectedCustomer?.email || selectedCustomer?.card_number || ''"></div>
                </div>
                <button @click="selectedCustomer = null; customerSearch = ''; rewardsCardInput = ''" class="text-red-400 hover:text-red-600 text-[10px] lg:text-xs">Remove</button>
            </div>
        </div>

        <div x-show="!selectedCustomer">
            <label class="block text-[11px] lg:text-sm font-medium text-gray-600 mb-1">Card Number / Phone / Name</label>
            <input type="text" x-model="rewardsCardInput" @keydown.enter="lookupRewardsCustomer()"
                   class="w-full p-2 lg:p-3 border-2 rounded-lg text-sm lg:text-base font-medium text-center focus:border-purple-500 focus:outline-none"
                   placeholder="Scan or type here..." x-ref="rewardsInput">

            <div class="flex gap-1.5 mt-2">
                <button @click="scanRewardsCard()" :disabled="!buddyConnected && !hasNativeBridge"
                        class="flex-1 py-2 lg:py-2.5 rounded-lg text-[10px] lg:text-sm font-medium flex items-center justify-center gap-1 transition-colors"
                        :class="buddyConnected || hasNativeBridge ? 'bg-purple-600 text-white hover:bg-purple-700' : 'bg-gray-200 text-gray-400 cursor-not-allowed'">
                    <svg class="w-3.5 h-3.5 lg:w-4 lg:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                    Scan QR/Barcode
                </button>
                <button @click="lookupRewardsCustomer()" :disabled="!rewardsCardInput"
                        class="flex-1 py-2 lg:py-2.5 bg-blue-600 text-white rounded-lg text-[10px] lg:text-sm font-medium hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed">
                    Look Up
                </button>
            </div>

            <div x-show="rewardsResults.length > 0" class="mt-2 max-h-32 overflow-y-auto border rounded-lg">
                <template x-for="c in rewardsResults" :key="c.id">
                    <button @click="selectRewardsCustomer(c)"
                            class="w-full text-left px-2 py-1.5 hover:bg-purple-50 border-b last:border-0 text-[11px] lg:text-sm">
                        <div class="font-medium" x-text="c.name"></div>
                        <div class="text-[10px] lg:text-xs text-gray-400" x-text="[c.card_number, c.phone, c.email].filter(Boolean).join(' · ')"></div>
                    </button>
                </template>
            </div>
            <div x-show="rewardsNoMatch" class="mt-2 text-center text-[10px] lg:text-xs text-red-500 bg-red-50 rounded p-2">No matching customer found.</div>
        </div>

        <div class="flex gap-2 mt-4">
            <button @click="showRewardsModal = false" class="flex-1 py-2 lg:py-3 bg-gray-200 text-gray-700 rounded-lg text-xs lg:text-base font-medium hover:bg-gray-300">Close</button>
        </div>
    </div>
</div>

<script>
function posApp() {
    return {
        screen: 'pos',
        posMode: localStorage.getItem('insapos_mode') || '',
        showModeSelect: !localStorage.getItem('insapos_mode'),
        retailPreviewMode: false,
        retailScanResult: null,
        retailScanQuery: '',
        showCameraScanner: false,
        _cameraStream: null,
        _cameraScanInterval: null,
        activeShift: null,
        products: [],
        productsCatalogMode: '',
        productCount: 0,
        _nativeGridOffset: 0,
        _productLru: {},
        PRODUCT_LRU_MAX: 32,
        categories: [],
        filteredProducts: [],
        filteredProductsTotal: 0,
        catalogNeedsCategory: false,
        gridDisplayLimit: 48,
        gridCanLoadMore: false,
        browserOnline: typeof navigator !== 'undefined' ? navigator.onLine : true,
        nativeSyncDetail: null,
        dashboardData: null,
        shiftSalesTotal: 0,
        shiftTransactionCount: 0,
        productsLoading: true,
        _syncEngineReady: false,
        _scanInputTimer: null,
        _filterRaf: null,
        _filterDebounceTimer: null,
        _searchCache: {},
        _productBySku: null,
        _productByBarcode: null,
        _scanSearchDelay: 500,
        _nativeSearchDelay: 300,
        LARGE_CATALOG_THRESHOLD: 200,
        searchQuery: '',
        selectedCategory: '',
        cart: [],
        paymentMethod: 'cash',
        amountTendered: 0,
        changeAmount: 0,
        paymentRef: '',
        showReceipt: false,
        lastSale: null,
        buddyConnected: false,
        toasts: [],
        _toastId: 0,

        showShiftOpenModal: false,
        showShiftCloseModal: false,
        showShiftResult: false,
        shiftCashInput: 0,
        shiftResultData: null,

        showItemDiscountModal: false,
        showOrderDiscountModal: false,
        discountEditIndex: -1,
        discountEditItem: null,
        discountType: 'percent',
        discountValue: 0,
        orderDiscountType: 'percent',
        orderDiscountValue: 0,
        orderDiscountApplied: 0,

        selectedCustomer: null,
        customerSearch: '',
        customerResults: [],
        showCustomerDropdown: false,

        showHistoryModal: false,
        recentSales: [],
        reprintingSaleKey: null,

        showPrinterModal: false,
        printerStep: 1,
        printerList: [],
        printerSelectedIndex: -1,
        printerScanning: false,
        printerTesting: false,
        printerSaving: false,
        printerStatusMessage: '',
        printerDefault: { connected: false, name: null, type: null },
        printerTestPassed: false,
        printerPaperSize: '57mm',
        printerFontMode: 'paper_size',
        printerLayoutHint: '',

        showIoModal: false,
        showPosSettingsModal: false,
        posSettingsLoading: false,
        settingsTestPrinting: false,
        cdSaving: false,
        cdPhotoUploading: false,
        cdVideoUploading: false,
        cdPhotoFile: null,
        cdVideoFile: null,
        cdPhotoPreview: null,
        posSettings: {
            app_version: '',
            device_model: '',
            network_online: true,
            customer_display_enabled: true,
            customer_display_available: false,
            customer_display_name: '',
            customer_display_orientation: 'auto',
            customer_display_rotation_mode: 'mix',
            customer_display_show_cart: '1',
            customer_display_photo: '',
            customer_display_video: '',
            last_sync_at: '',
            products_cached: 0,
            paper_size: '57mm',
            font_mode: 'paper_size',
            hardware_summary: '',
            allow_minimize: false,
        },
        ioMenuView: true,
        ioOption: null,
        ioStep: 1,
        ioScanning: false,
        ioTesting: false,
        ioSaving: false,
        ioStatusMessage: '',
        ioKeyboards: [],
        ioMice: [],
        ioScanners: [],
        ioSelectedKeyboardIndex: -1,
        ioSelectedMouseIndex: -1,
        ioSelectedScannerIndex: -1,
        ioApiAvailable: false,
        _customerDisplayTimer: null,
        useCameraForScan: localStorage.getItem('insapos_use_camera_for_scan') !== '0',
        _lastNativeScan: null,
        _lastNativeScanTime: 0,

        showRewardsModal: false,
        rewardsCardInput: '',
        rewardsResults: [],
        rewardsNoMatch: false,
        hasNativeBridge: false,
        silentSync: false,
        autoPrintReceipt: true,
        androidLocalUp: true,
        _nativeScanPort: 18182,
        _nativeCategoryStatCount: 0,
        _categoryEmptyWarned: false,
        _scanning: false,
        saleProcessing: false,
        licenseActive: @json($licenseActive ?? true),
        licenseBlocked: false,
        licenseBlockMessage: '',
        terminalSessionReady: false,
        _terminalSessionRedirectLogin: false,

        syncStatus: 'offline',
        pendingSyncCount: 0,
        posReady: false,
        offlineBanner: false,
        storeDownload: {
            active: false,
            title: 'Preparing POS…',
            message: 'Downloading catalog…',
            percent: 0,
        },
        catalogImport: {
            active: false,
            progress: 0,
            message: '',
            _wasActive: false,
        },
        showConflictModal: false,
        conflictItems: [],
        conflictLocalId: null,

        showReadingModal: false,
        readingData: null,
        showZConfirm: false,

        _barcodeBuffer: '',
        _barcodeTimer: null,
        headerClock: '',
        _clockTimer: null,

        config: {
            cashierId: {{ auth()->id() ?? 'null' }},
            branchId: {{ auth()->user()?->branch_id ?? 'null' }},
            role: @json(auth()->user()?->role),
            canViewShiftTotals: @json(auth()->user()?->canViewShiftTotals() ?? false),
            canEditCdSettings: @json($canEditCdSettings),
            cdRoutes: {
                show: @json(route('pos.customer-display.show')),
                update: @json(route('pos.customer-display.update')),
                photo: @json(route('pos.customer-display.photo')),
                video: @json(route('pos.customer-display.video')),
            },
        },

        canViewShiftTotals() {
            return !!this.config.canViewShiftTotals;
        },

        canManageShift() {
            const role = this.config.role || '';
            return ['owner', 'admin', 'manager'].includes(role);
        },

        updateHeaderClock() {
            this.headerClock = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        },

        paymentMethods: [
            { value: 'cash',        label: 'Cash',        icon: '💵' },
            { value: 'debit_card',  label: 'Debit Card',  icon: '💳' },
            { value: 'credit_card', label: 'Credit Card', icon: '💳' },
            { value: 'gcash',       label: 'GCash',       icon: '📱' },
            { value: 'maya',        label: 'Maya',        icon: '📱' },
            { value: 'palawanpay',  label: 'PalawanPay',  icon: '📱' },
        ],

        get cartSubtotal() { return this.cart.reduce((sum, i) => sum + (i.qty * i.price), 0); },
        get cartDiscount() {
            return this.cart.reduce((sum, i) => sum + (i.discount || 0), 0) + this.orderDiscountApplied;
        },
        get cartTotal() { return Math.max(0, this.cartSubtotal - this.cartDiscount); },
        get quickCashAmounts() {
            const total = this.cartTotal;
            const amounts = [50, 100, 200, 500, 1000, 2000];
            const rounded = Math.ceil(total / 100) * 100;
            if (rounded > 0 && !amounts.includes(rounded)) amounts.push(rounded);
            return [...new Set(amounts)].filter(a => a >= total).sort((a, b) => a - b).slice(0, 7);
        },
        get canProceed() {
            if (this.saleProcessing) return false;
            if (this.cart.length === 0) return false;
            if (this.paymentMethod === 'cash') return this.amountTendered >= this.cartTotal;
            return true;
        },
        get syncLabel() {
            return {
                'synced': 'Synced',
                'syncing': 'Syncing...',
                'downloading': 'Downloading…',
                'pushing': 'Uploading...',
                'pulling-products': 'Updating products…',
                'pulling-inventory': 'Updating stock…',
                'pulling-customers': 'Updating customers…',
                'offline': 'Offline',
                'partial': 'Pending',
                'error': 'Sync Error',
            }[this.syncStatus] || 'Unknown';
        },
        get syncStatusTitle() {
            if (this.syncStatus === 'synced') return 'All data synced. Click to sync now.';
            if (this.syncStatus === 'offline') return 'No server connection. Sales are saved locally.';
            if (this.pendingSyncCount > 0) return this.pendingSyncCount + ' transactions waiting to sync.';
            return 'Click to sync now.';
        },
        get ioWizardTitle() {
            if (this.ioOption === 'keyboard') return 'Keyboard / Mouse';
            if (this.ioOption === 'scanner') return 'Barcode Scanner';
            if (this.ioOption === 'camera') return 'Camera';
            return '';
        },

        selectMode(mode) {
            this.posMode = mode;
            this.showModeSelect = false;
            localStorage.setItem('insapos_mode', mode);
            if (mode === 'retail') {
                this.filteredProducts = [];
                this.setScanFieldValue('cafe', '');
                this.setScanFieldValue('retail', '');
                this.selectedCategory = '';
            } else {
                this.setScanFieldValue('retail', '');
                this.filterProducts();
            }
            this.$nextTick(() => {
                if (mode === 'retail') { const el = document.getElementById('retailScanInput'); if (el) el.focus(); }
            });
        },

        syncScanClearButton(inputEl) {
            if (!inputEl) return;
            const clearBtn = inputEl.parentElement?.querySelector('[data-scan-clear]');
            if (clearBtn) clearBtn.hidden = !inputEl.value.length;
        },

        /** Typing path: DOM-only until debounce — avoids Alpine re-render per keystroke. */
        onScanFieldInput(event, mode) {
            this.syncScanClearButton(event.target);
            clearTimeout(this._scanInputTimer);
            this._scanInputTimer = setTimeout(() => {
                this.commitScanFieldValue(event.target.value, mode, false);
            }, this._scanSearchDelay);
        },

        /** Enter / scanner wedge: commit immediately. */
        commitScanField(event, mode) {
            clearTimeout(this._scanInputTimer);
            this.commitScanFieldValue(event.target.value, mode, true);
        },

        commitScanFieldValue(val, mode, enterPressed) {
            if (mode === 'retail') {
                this.retailScanQuery = val;
                if (enterPressed) this.retailScan();
                else if (val.trim().length >= 3) this.scheduleRetailLiveSearch();
                else if (!val.trim().length) {
                    this.retailScanResult = null;
                    if (this.filteredProducts.length) this.filteredProducts = [];
                }
                return;
            }
            this.searchQuery = val;
            if (enterPressed) {
                this.scheduleFilterProducts();
            } else if (!val.trim().length || val.trim().length >= 3) {
                this.scheduleFilterProducts();
            }
        },

        setScanFieldValue(mode, val) {
            const id = mode === 'retail' ? 'retailScanInput' : 'posSearchInput';
            const el = document.getElementById(id);
            if (el) {
                el.value = val;
                this.syncScanClearButton(el);
            }
            if (mode === 'retail') this.retailScanQuery = val;
            else this.searchQuery = val;
        },

        scheduleRetailLiveSearch() {
            if (this._filterRaf) cancelAnimationFrame(this._filterRaf);
            this._filterRaf = requestAnimationFrame(() => {
                this._filterRaf = null;
                this.retailLiveSearch();
            });
        },

        scheduleFilterProducts() {
            clearTimeout(this._filterDebounceTimer);
            const delay = this.isStorageCatalog() ? this._nativeSearchDelay : 0;
            const run = () => {
                if (this._filterRaf) cancelAnimationFrame(this._filterRaf);
                this._filterRaf = requestAnimationFrame(() => {
                    this._filterRaf = null;
                    this.filterProducts();
                });
            };
            if (delay > 0) {
                this._filterDebounceTimer = setTimeout(run, delay);
            } else {
                run();
            }
        },

        rememberSearchCache(key, value) {
            this._searchCache[key] = value;
            const keys = Object.keys(this._searchCache);
            if (keys.length > 48) delete this._searchCache[keys[0]];
        },

        invalidateSearchCache() {
            this._searchCache = {};
            this._productBySku = null;
            this._productByBarcode = null;
        },

        toggleMode() {
            this.selectMode(this.posMode === 'cafe' ? 'retail' : 'cafe');
        },

        rebuildProductIndex() {
            const bySku = new Map();
            const byBarcode = new Map();
            for (let i = 0; i < this.products.length; i++) {
                const p = this.products[i];
                if (p.sku) bySku.set(String(p.sku), p);
                if (p.barcode) byBarcode.set(String(p.barcode), p);
            }
            this._productBySku = bySku;
            this._productByBarcode = byBarcode;
        },

        /** Exact barcode/SKU — SQLite on native tablets, in-memory map otherwise. */
        findProductExact(code) {
            if (!code) return null;
            const key = String(code);
            if (this.isStorageCatalog()) {
                return this.findProductExactNative(key);
            }
            if (this._productByBarcode) {
                return this._productByBarcode.get(key) || this._productBySku.get(key) || null;
            }
            return this.products.find(p => (p.barcode && p.barcode === key) || (p.sku && p.sku === key)) || null;
        },

        searchProductsLocal(query, limit = 30) {
            const q = query.trim();
            if (q.length < 3) return [];
            if (this.isStorageCatalog()) {
                return this.searchNativeProducts(q, limit);
            }
            const ql = q.toLowerCase();
            const results = [];
            for (let i = 0; i < this.products.length && results.length < limit; i++) {
                const p = this.products[i];
                if (p.name.toLowerCase().includes(ql) ||
                    (p.sku && p.sku.toLowerCase().includes(ql)) ||
                    (p.barcode && p.barcode.includes(q))) {
                    results.push(p);
                }
            }
            return results;
        },

        retailLiveSearch() {
            const q = this.retailScanQuery.trim();
            this.retailScanResult = null;
            if (q.length < 3) {
                if (this.filteredProducts.length) this.filteredProducts = [];
                return;
            }
            const cacheKey = 'r|' + q;
            if (this._searchCache[cacheKey]) {
                this.filteredProducts = this._searchCache[cacheKey];
                return;
            }
            const results = this.searchProductsLocal(q, 30);
            this.rememberSearchCache(cacheKey, results);
            this.filteredProducts = results;
        },

        retailScan() {
            const q = this.retailScanQuery.trim();
            if (!q) { this.retailScanResult = null; return; }
            const exact = this.findProductExact(q);
            if (exact) {
                if (this.retailPreviewMode) { this.retailScanResult = exact; this.filteredProducts = []; }
                else { this.retailAddToCart(exact); }
                return;
            }
            const fuzzy = q.length >= 3 ? this.searchProductsLocal(q, 31) : [];
            if (fuzzy.length === 1) {
                if (this.retailPreviewMode) { this.retailScanResult = fuzzy[0]; this.filteredProducts = []; }
                else { this.retailAddToCart(fuzzy[0]); }
            }
            else if (fuzzy.length > 1) { this.retailScanResult = null; this.filteredProducts = fuzzy.slice(0, 30); }
            else { this.retailScanResult = null; this.filteredProducts = []; this.showToast('Product not found: ' + q, 'warning'); }
        },

        retailAddToCart(product) {
            if (this.addToCart(product, true)) this.showToast(product.name + ' added', 'success', 1500);
            this.retailScanResult = null;
            this.setScanFieldValue('retail', '');
            this.filteredProducts = [];
            this.$nextTick(() => { const el = document.getElementById('retailScanInput'); if (el) el.focus(); });
        },

        retailCancel() {
            this.retailScanResult = null;
            this.setScanFieldValue('retail', '');
            this.filteredProducts = [];
            this.$nextTick(() => { const el = document.getElementById('retailScanInput'); if (el) el.focus(); });
        },

        get visibleProducts() {
            return (this.filteredProducts || []).slice(0, this.gridDisplayLimit);
        },

        async init() {
            document.body.classList.remove('insapos-alpine-pending');
            window.posAppInstance = this;
            this.browserOnline = navigator.onLine;
            window.addEventListener('online', () => { this.browserOnline = true; this.updateOfflineBanner(); });
            window.addEventListener('offline', () => { this.browserOnline = false; this.updateOfflineBanner(); });
            document.addEventListener('insapos:syncStatus', (e) => this.applyNativeSyncStatus(e.detail || {}));
            document.addEventListener('insapos:catalogImport', (e) => this.applyCatalogImportStatus(e.detail || {}));
            document.addEventListener('insapos:dashboardData', (e) => this.applyDashboardData(e.detail || {}));
            document.addEventListener('insapos:openSettings', () => this.openPosSettings());
            document.addEventListener('insapos:openPrinter', () => this.openPrinterSettings());
            document.addEventListener('insapos:ready', () => {
                this.hasNativeBridge = typeof window.INSAPOS !== 'undefined';
                if (this.hasNativeBridge) {
                    this.buddyConnected = true;
                    this.ioApiAvailable = true;
                    if (typeof INSABuddy !== 'undefined') INSABuddy.detectV2();
                }
            });
            document.addEventListener('insapos:hardwareReady', () => {
                this.hasNativeBridge = typeof window.INSAPOS !== 'undefined';
                if (this.hasNativeBridge) {
                    this.buddyConnected = true;
                    this.ioApiAvailable = true;
                }
            });
            this.updateHeaderClock();
            this._clockTimer = setInterval(() => this.updateHeaderClock(), 1000);
            this.hasNativeBridge = typeof window.INSAPOS !== 'undefined';
            this.silentSync = this.hasNativeBridge;
            if (this.hasNativeBridge) {
                if (typeof INSABuddy !== 'undefined') INSABuddy.detectV2();
                this.buddyConnected = true;
                this.ioApiAvailable = true;
                try { this._nativeScanPort = window.INSAPOS.getServicePort() || 18182; } catch { this._nativeScanPort = 18182; }
                try {
                    const storedAuto = localStorage.getItem('insapos_auto_print_receipt');
                    if (storedAuto !== null) this.autoPrintReceipt = storedAuto === '1';
                } catch {}
                if (this.config.branchId && typeof window.INSAPOS.setBranchId === 'function') {
                    try { window.INSAPOS.setBranchId(this.config.branchId); } catch (e) {}
                }
                if (this.config.cashierId && typeof window.INSAPOS.setCashierId === 'function') {
                    try { window.INSAPOS.setCashierId(this.config.cashierId); } catch (e) {}
                }
                try {
                    if (window.INSA_IS_SUPER_ADMIN && window.INSAPOS.notifySuperAdminStatus) {
                        window.INSAPOS.notifySuperAdminStatus(true);
                    }
                } catch (_) {}
                this.checkAndroidLocalHealth();
            }
            if (!this.licenseActive) return;
            const seatOk = await this.registerTerminalSession();
            if (!seatOk) {
                if (this._terminalSessionRedirectLogin) {
                    window.location.href = '/login?error=session_lost';
                }
                return;
            }
            await this.initOffline();
            this.loadShift();
            this.initBuddy();
            window.onINSAPOSBarcode = (barcode) => { this._lastNativeScanTime = Date.now(); this._lastNativeScan = barcode; this.handleBarcodeScan(barcode); };
            window.onINSAPOSLocalSaleResult = (requestId, data) => {
                const pending = window.__insaposSalePending;
                if (!pending || !pending[requestId]) return;
                const entry = pending[requestId];
                clearTimeout(entry.timeout);
                delete pending[requestId];
                entry.resolve(data);
            };
            this.bindScanInputFocusBridge();
            await this.loadIoPreferences();
            if (this.hasNativeBridge && typeof INSABuddy !== 'undefined') {
                try {
                    const cd = await INSABuddy.getCustomerDisplayStatus();
                    if (cd && cd.ok !== false) {
                        this.posSettings.customer_display_available = !!cd.available;
                        this.posSettings.customer_display_name = cd.display_name || '';
                        if (cd.available) {
                            this.posSettings.customer_display_enabled = true;
                            if (cd.enabled === false && typeof window.INSAPOS.setCustomerDisplayEnabled === 'function') {
                                try { window.INSAPOS.setCustomerDisplayEnabled(true); } catch (e) {}
                            }
                        } else {
                            this.posSettings.customer_display_enabled = cd.enabled !== false;
                        }
                    }
                } catch {}
                this.scheduleCustomerDisplaySync(true);
            }
            this.$watch('cart', () => this.scheduleCustomerDisplaySync(), { deep: true });
            this.$watch('orderDiscountApplied', () => this.scheduleCustomerDisplaySync());
            this.$watch('orderDiscountValue', () => this.scheduleCustomerDisplaySync());
        },

        bindScanInputFocusBridge() {
            const setScanFocus = (on) => {
                if (typeof INSAPOS !== 'undefined' && typeof INSAPOS.setScanInputFocused === 'function') {
                    try { INSAPOS.setScanInputFocused(!!on); } catch (e) {}
                }
            };
            document.addEventListener('focusin', (e) => {
                if (e.target && e.target.matches && e.target.matches('[data-scan-input]')) setScanFocus(true);
            });
            document.addEventListener('focusout', () => {
                setTimeout(() => {
                    const active = document.activeElement;
                    setScanFocus(active && active.matches && active.matches('[data-scan-input]'));
                }, 0);
            });
        },

        async registerTerminalSession() {
            if (typeof PosTerminalSession === 'undefined') {
                this.terminalSessionReady = true;
                return true;
            }
            const result = await PosTerminalSession.register(this.config.branchId);
            if (result.ok) {
                this.licenseBlocked = false;
                this.terminalSessionReady = true;
                if (typeof window.INSAPOS !== 'undefined' && typeof window.INSAPOS.setTerminalSessionId === 'function') {
                    try { window.INSAPOS.setTerminalSessionId(result.sessionId || ''); } catch (e) {}
                }
                return true;
            }
            if (result.code === 'session_expired' || result.redirectLogin) {
                this._terminalSessionRedirectLogin = true;
                return false;
            }
            if (result.code === 'license_inactive') {
                this.licenseActive = false;
                this.licenseBlocked = false;
                return false;
            }
            this.licenseBlocked = true;
            this.licenseBlockMessage = result.message || 'License limit reached.';
            return false;
        },

        async retryTerminalSession() {
            this.licenseBlocked = false;
            await this.registerTerminalSession();
            if (!this.licenseBlocked) {
                await this.initOffline();
                this.loadShift();
            }
        },

        useNativeEngine() {
            return this.useLocalPosEngine();
        },

        useLocalPosEngine() {
            return this.hasNativeBridge &&
                typeof window.INSAPOS !== 'undefined' &&
                typeof window.INSAPOS.createLocalSale === 'function';
        },

        applyDashboardData(detail) {
            this.dashboardData = detail;
            if (detail && detail.products_cached) {
                this.posSettings.products_cached = detail.products_cached;
            }
        },

        applyCatalogImportStatus(detail) {
            if (!detail) return;
            const state = (detail.state || '').toLowerCase();
            const wasActive = this.catalogImport._wasActive;
            this.catalogImport.active = state === 'downloading' || state === 'importing';
            this.catalogImport.progress = detail.progress || 0;
            this.catalogImport.message = detail.message || (this.catalogImport.active ? 'Catalog updating…' : '');
            if (this.catalogImport.active) {
                this.catalogImport._wasActive = true;
            }
            if (wasActive && !this.catalogImport.active && (state === 'ready' || state === 'idle')) {
                this.catalogImport._wasActive = false;
                this.refreshNativeProductCount();
                this.loadNativeCategories();
                if (this.isStorageCatalog()) {
                    this.filterProducts();
                }
            }
        },

        async pollCatalogImportStatus() {
            if (!this.useNativeEngine()) return;
            try {
                if (typeof window.INSAPOS.getCatalogImportStatus !== 'function') return;
                const raw = window.INSAPOS.getCatalogImportStatus();
                const detail = typeof raw === 'string' ? JSON.parse(raw) : raw;
                if (detail) this.applyCatalogImportStatus(detail);
            } catch (e) {}
        },

        applyNativeSyncStatus(detail) {
            this.nativeSyncDetail = detail;
            if (detail.catalog_import) {
                this.applyCatalogImportStatus(detail.catalog_import);
            }
            if (this.silentSync) {
                if (typeof detail.unsynced_count === 'number') {
                    this.pendingSyncCount = detail.unsynced_count + (detail.sync_queue_count || 0);
                }
                return;
            }
            if (typeof detail.unsynced_count === 'number') {
                this.pendingSyncCount = detail.unsynced_count + (detail.sync_queue_count || 0);
            }
            if (detail.online === false) {
                this.syncStatus = detail.unsynced_count > 0 ? 'partial' : 'offline';
                this.offlineBanner = true;
                return;
            }
            const st = (detail.engine_status || detail.status || '').toLowerCase();
            if (st === 'pushing') this.syncStatus = 'pushing';
            else if (st === 'pulling' && !this.posReady) this.syncStatus = 'syncing';
            else if (st === 'partial' || (st === 'idle' && detail.unsynced_count > 0)) this.syncStatus = 'partial';
            else if (st === 'error') this.syncStatus = 'error';
            else if (st === 'idle') this.syncStatus = detail.unsynced_count > 0 ? 'partial' : 'synced';
        },

        renderExpiryBadge(product) {
            if (!product) return '';
            if (product.near_expiry) return 'Exp soon';
            if (product.earliest_expiry) {
                const d = String(product.earliest_expiry).slice(0, 10);
                return 'Exp ' + d;
            }
            return '';
        },

        mapNativeProductRow(p) {
            let dj = p.data_json;
            if (typeof dj === 'string') {
                try { dj = JSON.parse(dj); } catch { dj = null; }
            }
            return {
                id: p.server_id || p.id,
                name: p.name,
                barcode: p.barcode || '',
                sku: p.sku || dj?.sku || '',
                price: parseFloat(p.price || 0),
                stock: parseFloat(p.stock || 0),
                category: p.category || dj?.category || '',
                category_id: p.category_id || dj?.category_id || null,
            };
        },

        isStorageCatalog() {
            return this.productsCatalogMode === 'storage' && this.useNativeEngine();
        },

        parseNativeProductsPayload(raw) {
            const data = typeof raw === 'string' ? JSON.parse(raw) : raw;
            if (!data || !data.ok) return { items: [], total: 0, hasMore: false };
            const products = Array.isArray(data.products) ? data.products : Object.values(data.products || {});
            const items = [];
            for (const p of products) {
                const row = this.mapNativeProductRow(p);
                if (row.id) items.push(row);
            }
            return {
                items,
                total: data.total ?? items.length,
                hasMore: !!data.has_more,
            };
        },

        cacheProductLru(product) {
            if (!product || !product.id) return;
            const key = String(product.id);
            this._productLru[key] = product;
            const keys = Object.keys(this._productLru);
            if (keys.length > this.PRODUCT_LRU_MAX) delete this._productLru[keys[0]];
            if (product.barcode) this._productLru[String(product.barcode)] = product;
            if (product.sku) this._productLru[String(product.sku)] = product;
        },

        findProductInLru(code) {
            return this._productLru[String(code)] || null;
        },

        fetchNativeProductsPage(query, offset, limit, categoryId) {
            if (!this.useNativeEngine()) return { items: [], total: 0, hasMore: false };
            try {
                const cat = categoryId ? parseInt(categoryId, 10) : 0;
                let raw;
                if (typeof window.INSAPOS.getLocalProductsPage === 'function') {
                    raw = window.INSAPOS.getLocalProductsPage(query || '', offset, limit, cat);
                } else {
                    raw = window.INSAPOS.getLocalProducts(query || '');
                }
                const parsed = this.parseNativeProductsPayload(raw);
                parsed.items.forEach((p) => this.cacheProductLru(p));
                return parsed;
            } catch (e) {
                console.warn('[pos] native page fetch failed:', e);
                return { items: [], total: 0, hasMore: false };
            }
        },

        searchNativeProducts(query, limit) {
            if (!this.useNativeEngine()) return [];
            try {
                let raw;
                if (typeof window.INSAPOS.searchProducts === 'function') {
                    raw = window.INSAPOS.searchProducts(query, limit);
                } else {
                    raw = window.INSAPOS.getLocalProductsPage(query, 0, limit, 0);
                }
                const parsed = this.parseNativeProductsPayload(raw);
                parsed.items.forEach((p) => this.cacheProductLru(p));
                return parsed.items;
            } catch (e) {
                console.warn('[pos] native search failed:', e);
                return [];
            }
        },

        findProductExactNative(code) {
            const cached = this.findProductInLru(code);
            if (cached) return cached;
            try {
                if (typeof window.INSAPOS.getProductByBarcode === 'function') {
                    const raw = window.INSAPOS.getProductByBarcode(code);
                    const data = typeof raw === 'string' ? JSON.parse(raw) : raw;
                    if (data.ok && data.product) {
                        const row = this.mapNativeProductRow(data.product);
                        this.cacheProductLru(row);
                        return row;
                    }
                }
                const hits = this.searchNativeProducts(code, 8);
                const key = String(code);
                return hits.find((p) => p.barcode === key || p.sku === key) || hits[0] || null;
            } catch (e) {
                console.warn('[pos] native exact lookup failed:', e);
                return null;
            }
        },

        async refreshNativeProductCount() {
            if (!this.useNativeEngine()) return 0;
            try {
                if (typeof window.INSAPOS.getOfflineStats === 'function') {
                    const raw = window.INSAPOS.getOfflineStats();
                    const stats = typeof raw === 'string' ? JSON.parse(raw) : raw;
                    this.productCount = stats.products || 0;
                    this.posSettings.products_cached = this.productCount;
                    if (typeof stats.categories === 'number') {
                        this._nativeCategoryStatCount = stats.categories;
                    }
                    return this.productCount;
                }
            } catch (e) {
                console.warn('[pos] native stats failed:', e);
            }
            return this.productCount;
        },

        loadNativeCategories() {
            if (!this.useNativeEngine()) return 0;
            try {
                if (typeof window.INSAPOS.getLocalCategories === 'function') {
                    const raw = window.INSAPOS.getLocalCategories();
                    const data = typeof raw === 'string' ? JSON.parse(raw) : raw;
                    if (data.ok && data.categories && data.categories.length) {
                        this.categories = data.categories;
                        this._categoryEmptyWarned = false;
                        return data.categories.length;
                    }
                }
            } catch (e) {
                console.warn('[pos] native categories failed:', e);
            }
            return this.categories.length || 0;
        },

        warnIfNativeCategoriesMissing() {
            if (!this.useNativeEngine() || this.posMode !== 'cafe') return;
            const loaded = this.categories.length;
            const statCats = this._nativeCategoryStatCount || 0;
            if (loaded === 0 && statCats > 0 && !this._categoryEmptyWarned) {
                this._categoryEmptyWarned = true;
                this.showToast('Categories are on device but the dropdown is empty — tap Sync or reload the page', 'warning', 5000);
            }
        },

        async initNativeStorageCatalog() {
            if (!this.useNativeEngine()) return false;
            this.productsCatalogMode = 'storage';
            this.products = [];
            this.invalidateSearchCache();
            await this.refreshNativeProductCount();
            this.loadNativeCategories();
            if (this.productCount > 0) {
                this.productsLoading = false;
                this._nativeGridOffset = 0;
                this.gridDisplayLimit = 48;
                this.filterProducts();
                return true;
            }
            const first = this.fetchNativeProductsPage('', 0, 48, this.selectedCategory);
            if (first.items.length > 0) {
                this.productCount = first.total || first.items.length;
                this.productsLoading = false;
                this.filterProducts();
                return true;
            }
            return false;
        },

        async checkAndroidLocalHealth() {
            if (!this.hasNativeBridge) return;
            const ping = async () => {
                const controller = new AbortController();
                setTimeout(() => controller.abort(), 2500);
                const res = await fetch(`http://127.0.0.1:${this._nativeScanPort}/ping`, { signal: controller.signal });
                return res.ok;
            };
            const retryCategoriesIfNeeded = () => {
                if (!this.categories.length) {
                    this.loadNativeCategories();
                    this.warnIfNativeCategoriesMissing();
                }
            };
            try {
                if (await ping()) {
                    this.androidLocalUp = true;
                    retryCategoriesIfNeeded();
                    return;
                }
                await new Promise((r) => setTimeout(r, 1500));
                this.androidLocalUp = (await ping()) || this.hasNativeBridge;
                if (this.androidLocalUp) retryCategoriesIfNeeded();
            } catch {
                this.androidLocalUp = this.hasNativeBridge;
            }
        },

        onStoreDownloadProgress(p) {
            if (this.silentSync || this.posReady) return;
            if (!p) return;
            if (p.phase === 'offline') {
                this.offlineBanner = true;
                this.storeDownload.message = p.message || 'Using cached store data';
                this.storeDownload.percent = 100;
                return;
            }
            if (!this.posReady) {
                this.syncStatus = 'downloading';
            }
            this.storeDownload.message = p.message || 'Downloading store data…';
            if (typeof p.percent === 'number') this.storeDownload.percent = p.percent;
        },

        async finishStoreDownload(result) {
            this.storeDownload.active = false;
            this.storeDownload.percent = 100;
            if (this.isStorageCatalog()) {
                await this.refreshNativeProductCount();
                this.loadNativeCategories();
                const count = this.productCount;
                this.posReady = count > 0 || (result && result.online === false);
            } else {
                await this.refreshProductsFromDB();
            }
            const count = this.isStorageCatalog() ? this.productCount : this.products.length;
            this.posReady = count > 0 || (result && result.online === false);
            if (!this.posReady) {
                this.posReady = true;
                this.showToast('No products in catalog yet — you can still open the register', 'warning', 4000);
            }
            if (result && result.online === false) {
                this.offlineBanner = true;
                this.syncStatus = 'offline';
            } else if (count > 0) {
                this.syncStatus = 'synced';
                if (!localStorage.getItem('insapos_mode')) {
                    this.showToast('Store data ready (' + count + ' products)', 'success', 2500);
                }
            }
            if (this.hasNativeBridge && typeof window.INSAPOS !== 'undefined' && result && result.online === true) {
                setTimeout(() => {
                    try {
                        if (typeof window.INSAPOS.triggerLocalSync === 'function') {
                            window.INSAPOS.triggerLocalSync();
                        }
                    } catch (e) {}
                }, 6000);
            }
        },

        async initOffline() {
            const db = window.INSADB;
            let hadCache = false;
            if (db) {
                await db.init();
                this.pendingSyncCount = await db.transactions.pendingCount();
                hadCache = await this.loadProductsFromCache();
                if (hadCache) {
                    this.posReady = true;
                }
            }

            if (window.SyncEngine && !this._syncEngineReady) {
                this._syncEngineReady = true;
                SyncEngine.setBranchId(this.config.branchId);
                SyncEngine.on('syncStatus', (s) => {
                    if (this.silentSync) return;
                    if (s !== 'syncing') this.syncStatus = s;
                    if (s === 'offline') this.offlineBanner = true;
                });
                SyncEngine.on('connectivity', (o) => {
                    if (this.silentSync) return;
                    if (!o) {
                        this.syncStatus = 'offline';
                        this.offlineBanner = true;
                    } else {
                        this.offlineBanner = false;
                    }
                });
                SyncEngine.on('downloadStart', () => {
                    if (this.silentSync || this.posReady) return;
                    this.storeDownload.active = true;
                    this.storeDownload.title = 'Preparing POS…';
                    this.storeDownload.percent = 0;
                    this.storeDownload.message = 'Downloading store data…';
                });
                SyncEngine.on('downloadProgress', (p) => this.onStoreDownloadProgress(p));
                SyncEngine.on('downloadComplete', (r) => this.finishStoreDownload(r));
                SyncEngine.on('transactionSynced', () => {
                    if (!this.silentSync) {
                        this.pendingSyncCount = Math.max(0, this.pendingSyncCount - 1);
                        this.showToast('Transaction synced', 'success');
                    }
                });
                SyncEngine.on('syncComplete', async (d) => {
                    if (!this.silentSync) this.pendingSyncCount = d.pendingCount;
                });
                SyncEngine.on('conflict', (d) => { this.conflictItems = d.conflict; this.conflictLocalId = d.local_id; this.showConflictModal = true; });
                SyncEngine.on('productsUpdated', (c) => {
                    if (c > 0) {
                        this.refreshProductsFromDB();
                        if (this.isStorageCatalog()) this.loadNativeCategories();
                    }
                });
                SyncEngine.on('buddyRecovered', () => { if (!this.silentSync) this.showToast('Recovered offline data from INSABuddy', 'info'); });
                SyncEngine.on('syncError', (d) => { if (!this.silentSync) this.showToast('Sync error: ' + (d.error || 'Unknown'), 'error'); });
                if (this.useNativeEngine()) {
                    const nativeReady = await this.initNativeStorageCatalog();
                    SyncEngine.init({ branchId: this.config.branchId, skipInitialDownload: true });
                    if (nativeReady) {
                        this.posReady = true;
                        this.syncStatus = 'synced';
                        this._catalogPollTimer = setInterval(() => this.pollCatalogImportStatus(), 8000);
                        if (typeof window.INSAPOS.triggerLocalSync === 'function') {
                            setTimeout(() => {
                                try { window.INSAPOS.triggerLocalSync(); } catch (e) {}
                            }, 8000);
                        }
                    } else {
                        await SyncEngine.downloadAll({ force: !hadCache, silent: hadCache });
                    }
                } else {
                    SyncEngine.init({ branchId: this.config.branchId, skipInitialDownload: true });
                    await SyncEngine.downloadAll({ force: !hadCache, silent: hadCache });
                }
            } else {
                this.posReady = true;
                this.loadProducts();
            }

            if (this.posReady && (this.productCount > 0 || this.products.length > 0)) {
                this.productsLoading = false;
            }
        },

        handleBarcodeKey(event) {
            const tag = event.target.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
            if (event.key === 'Enter') {
                if (this._barcodeBuffer.length >= 3) {
                    if (this._lastNativeScan === this._barcodeBuffer && Date.now() - (this._lastNativeScanTime || 0) < 500) { this._barcodeBuffer = ''; clearTimeout(this._barcodeTimer); return; }
                    event.preventDefault(); this.handleBarcodeScan(this._barcodeBuffer);
                }
                this._barcodeBuffer = ''; clearTimeout(this._barcodeTimer); return;
            }
            if (event.key.length === 1) {
                this._barcodeBuffer += event.key;
                clearTimeout(this._barcodeTimer);
                this._barcodeTimer = setTimeout(() => { this._barcodeBuffer = ''; }, 100);
            }
        },

        handleBarcodeScan(barcode) {
            if (!barcode || barcode.length < 2) return;
            const product = this.findProductExact(barcode);
            if (this.posMode === 'retail') {
                if (product) {
                    if (this.retailPreviewMode) { this.retailScanResult = product; this.setScanFieldValue('retail', barcode); }
                    else { if (this.addToCart(product, true)) this.showToast(product.name + ' added', 'success', 1500); }
                } else { this.setScanFieldValue('retail', barcode); this.showToast('Product not found: ' + barcode, 'warning'); }
                return;
            }
            if (product) { if (this.addToCart(product, true)) this.showToast(product.name + ' added', 'success', 1500); }
            else { this.setScanFieldValue('cafe', barcode); this.filterProducts(); this.showToast('Product not found: ' + barcode, 'warning'); }
        },

        showToast(message, type = 'info', duration = 3000) {
            const id = ++this._toastId;
            this.toasts.push({ id, message, type });
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, duration);
        },

        initBuddy() {
            if (typeof INSABuddy === 'undefined') return;
            INSABuddy.detectV2();
            if (this.hasNativeBridge) {
                this.buddyConnected = true;
            }
            const pollMs = this.hasNativeBridge ? 120000 : 45000;
            const startDelay = this.hasNativeBridge ? 8000 : 0;
            setTimeout(() => {
                INSABuddy.detectV2();
                INSABuddy.startPolling(pollMs, (c) => {
                    this.buddyConnected = this.hasNativeBridge || c;
                });
            }, startDelay);
        },

        openPosSettings() {
            this.showPosSettingsModal = true;
            this.posSettingsLoading = true;
            this.$nextTick(() => this.loadPosSettings());
        },

        async loadPosSettings() {
            this.posSettingsLoading = true;
            try {
                if (typeof INSABuddy !== 'undefined') INSABuddy.detectV2();
                let data = null;
                if (this.hasNativeBridge && typeof window.INSAPOS !== 'undefined' && typeof window.INSAPOS.getPosSettings === 'function') {
                    const raw = window.INSAPOS.getPosSettings();
                    data = typeof raw === 'string' ? JSON.parse(raw) : raw;
                } else if (typeof INSABuddy !== 'undefined') {
                    data = await INSABuddy.getPosSettingsSummary();
                }
                if (data && data.ok !== false) {
                    const cd = data.customer_display || {};
                    this.posSettings.app_version = data.app_version || '';
                    this.posSettings.device_model = data.device ? `${data.device.manufacturer || ''} ${data.device.model || ''}`.trim() : '';
                    this.posSettings.network_online = data.network_online !== false && this.browserOnline;
                    this.posSettings.customer_display_enabled = cd.enabled !== false;
                    this.posSettings.customer_display_available = !!cd.available;
                    this.posSettings.customer_display_name = cd.display_name || '';
                    this.posSettings.last_sync_at = data.last_sync_at || '';
                    this.posSettings.paper_size = data.paper_size || this.printerPaperSize || '57mm';
                    this.posSettings.font_mode = data.font_mode || this.printerFontMode || 'paper_size';
                    this.posSettings.allow_minimize = !!data.allow_minimize;
                    this.posSettings.products_cached = data.products_cached
                        || this.dashboardData?.products_cached
                        || 0;
                    this.printerPaperSize = this.posSettings.paper_size;
                    this.printerFontMode = this.posSettings.font_mode;
                    const cdSettings = cd.settings || cd;
                    if (cdSettings.orientation) this.posSettings.customer_display_orientation = cdSettings.orientation;
                    if (cdSettings.rotation_mode) this.posSettings.customer_display_rotation_mode = cdSettings.rotation_mode;
                    if (cdSettings.show_cart !== undefined) {
                        this.posSettings.customer_display_show_cart = cdSettings.show_cart === true || cdSettings.show_cart === '1' ? '1' : '0';
                    }
                    if (cdSettings.photo) this.posSettings.customer_display_photo = cdSettings.photo;
                    if (cdSettings.video) this.posSettings.customer_display_video = cdSettings.video;
                }
                await this.loadCustomerDisplayServerSettings();
                if (this.dashboardData?.products_cached) {
                    this.posSettings.products_cached = this.dashboardData.products_cached;
                }
            } catch (e) {
                console.warn('[pos] loadPosSettings', e);
            } finally {
                this.posSettingsLoading = false;
            }
        },

        async loadCustomerDisplayServerSettings() {
            try {
                const res = await fetch(this.config.cdRoutes.show, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) return;
                const data = await res.json();
                if (!data.success || !data.settings) return;
                const s = data.settings;
                this.posSettings.customer_display_enabled = s.enabled === '1' || s.enabled === true;
                this.posSettings.customer_display_orientation = s.orientation || 'auto';
                this.posSettings.customer_display_rotation_mode = s.rotation_mode || 'mix';
                this.posSettings.customer_display_show_cart = (s.show_cart === '0' || s.show_cart === false) ? '0' : '1';
                if (s.photo) this.posSettings.customer_display_photo = s.photo;
                if (s.video) this.posSettings.customer_display_video = s.video;
            } catch (e) {
                console.warn('[pos] loadCustomerDisplayServerSettings', e);
            }
        },

        cdMediaFilename(url) {
            if (!url) return '';
            try {
                const part = String(url).split('/').pop() || '';
                return decodeURIComponent(part.split('?')[0]);
            } catch {
                return String(url);
            }
        },

        onCdPhotoSelected(event) {
            const file = event.target.files?.[0] || null;
            this.cdPhotoFile = file;
            this.cdPhotoPreview = file ? URL.createObjectURL(file) : null;
        },

        onCdVideoSelected(event) {
            this.cdVideoFile = event.target.files?.[0] || null;
        },

        async uploadCdPhoto() {
            if (!this.cdPhotoFile || !this.config.canEditCdSettings) return;
            this.cdPhotoUploading = true;
            try {
                const fd = new FormData();
                fd.append('photo', this.cdPhotoFile);
                const res = await fetch(this.config.cdRoutes.photo, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: fd,
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    this.showToast(data.message || 'Photo upload failed', 'error');
                    return;
                }
                if (data.url) this.posSettings.customer_display_photo = data.url;
                this.cdPhotoFile = null;
                this.cdPhotoPreview = null;
                this.showToast(data.message || 'Photo uploaded', 'success');
                await this.reloadCustomerDisplayAfterSave();
            } catch {
                this.showToast('Photo upload failed', 'error');
            } finally {
                this.cdPhotoUploading = false;
            }
        },

        async uploadCdVideo() {
            if (!this.cdVideoFile || !this.config.canEditCdSettings) return;
            this.cdVideoUploading = true;
            try {
                const fd = new FormData();
                fd.append('video', this.cdVideoFile);
                const res = await fetch(this.config.cdRoutes.video, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: fd,
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    this.showToast(data.message || 'Video upload failed', 'error');
                    return;
                }
                if (data.url) this.posSettings.customer_display_video = data.url;
                this.cdVideoFile = null;
                this.showToast(data.message || 'Video uploaded', 'success');
                await this.reloadCustomerDisplayAfterSave();
            } catch {
                this.showToast('Video upload failed', 'error');
            } finally {
                this.cdVideoUploading = false;
            }
        },

        async saveCustomerDisplaySettings() {
            if (!this.config.canEditCdSettings) return;
            this.cdSaving = true;
            try {
                const payload = {
                    enabled: !!this.posSettings.customer_display_enabled,
                    orientation: this.posSettings.customer_display_orientation,
                    rotation_mode: this.posSettings.customer_display_rotation_mode,
                    show_cart: this.posSettings.customer_display_show_cart === '1',
                };
                const res = await fetch(this.config.cdRoutes.update, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    this.showToast(data.message || 'Could not save customer display settings', 'error');
                    return;
                }
                if (data.settings) {
                    const s = data.settings;
                    this.posSettings.customer_display_enabled = s.enabled === '1' || s.enabled === true;
                    this.posSettings.customer_display_orientation = s.orientation || this.posSettings.customer_display_orientation;
                    this.posSettings.customer_display_rotation_mode = s.rotation_mode || this.posSettings.customer_display_rotation_mode;
                    this.posSettings.customer_display_show_cart = (s.show_cart === '0' || s.show_cart === false) ? '0' : '1';
                }
                if (this.hasNativeBridge && typeof window.INSAPOS !== 'undefined' && typeof window.INSAPOS.setCustomerDisplayEnabled === 'function') {
                    try {
                        window.INSAPOS.setCustomerDisplayEnabled(!!this.posSettings.customer_display_enabled);
                    } catch (e) {}
                }
                await this.reloadCustomerDisplayAfterSave();
                this.showToast('Customer display settings saved', 'success', 2000);
            } catch {
                this.showToast('Could not save customer display settings', 'error');
            } finally {
                this.cdSaving = false;
            }
        },

        async saveCustomerDisplayEnabled() {
            if (!this.config.canEditCdSettings) return;
            if (this.hasNativeBridge && typeof window.INSAPOS !== 'undefined' && typeof window.INSAPOS.setCustomerDisplayEnabled === 'function') {
                try {
                    const raw = window.INSAPOS.setCustomerDisplayEnabled(!!this.posSettings.customer_display_enabled);
                    const data = typeof raw === 'string' ? JSON.parse(raw) : raw;
                    if (data && data.ok === false) {
                        this.showToast('Could not update customer display on device', 'warning');
                    }
                } catch {
                    this.showToast('Could not update customer display on device', 'warning');
                }
            }
            try {
                const res = await fetch(this.config.cdRoutes.update, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ enabled: !!this.posSettings.customer_display_enabled }),
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.showToast(this.posSettings.customer_display_enabled ? 'Customer display enabled' : 'Customer display disabled', 'success', 1500);
                    if (this.posSettings.customer_display_enabled) this.syncCustomerDisplayCart();
                    else this.pushCustomerDisplay('welcome');
                }
            } catch {
                this.showToast('Could not save customer display setting', 'warning');
            }
        },

        async reloadCustomerDisplayAfterSave() {
            if (typeof INSABuddy !== 'undefined') {
                try {
                    const result = await INSABuddy.reloadCustomerDisplaySettings();
                    if (result && result.ok) return;
                } catch (e) {}
            }
            if (typeof window.INSAPOS !== 'undefined' && typeof window.INSAPOS.triggerLocalSync === 'function') {
                try { window.INSAPOS.triggerLocalSync(); } catch (e) {}
            } else if (typeof INSABuddy !== 'undefined') {
                try { await INSABuddy.triggerSync(); } catch (e) {}
            }
            if (this.posSettings.customer_display_enabled) {
                setTimeout(() => this.syncCustomerDisplayCart(), 500);
            }
        },

        saveAutoPrintSetting() {
            localStorage.setItem('insapos_auto_print_receipt', this.autoPrintReceipt ? '1' : '0');
            this.showToast(this.autoPrintReceipt ? 'Auto-print enabled' : 'Auto-print disabled', 'success', 1500);
        },

        async testCustomerDisplay() {
            if (typeof INSABuddy !== 'undefined') {
                const result = await INSABuddy.testCustomerDisplay();
                if (result && result.ok) {
                    this.showToast('Test sent to customer display', 'success');
                    return;
                }
            }
            this.showToast('Customer display test failed', 'warning');
        },

        syncCustomerDisplayCart() {
            this.pushCustomerDisplay(this.cart.length ? 'cart' : 'welcome');
        },

        scheduleCustomerDisplaySync(immediate = false) {
            if (this._customerDisplayTimer) clearTimeout(this._customerDisplayTimer);
            if (immediate) {
                this.syncCustomerDisplayCart();
                return;
            }
            this._customerDisplayTimer = setTimeout(() => {
                this._customerDisplayTimer = null;
                this.syncCustomerDisplayCart();
            }, 75);
        },

        pushCustomerDisplay(mode, extra = {}) {
            if (!this.hasNativeBridge) return;
            if (!this.posSettings.customer_display_enabled && !this.posSettings.customer_display_available) return;
            const payload = {
                mode,
                store_name: '{{ $brandName }}',
                items: this.cart.map(i => ({
                    name: i.product_name,
                    qty: i.qty,
                    price: i.price,
                    lineTotal: i.qty * i.price,
                })),
                subtotal: this.cartSubtotal,
                discount: this.cartDiscount,
                total: this.cartTotal,
                ...extra,
            };
            if (typeof INSABuddy !== 'undefined') {
                INSABuddy.updateCustomerDisplay(payload);
            }
        },

        async savePosPaperSettings() {
            this.printerPaperSize = this.posSettings.paper_size;
            this.printerFontMode = this.posSettings.font_mode;
            if (typeof INSABuddy !== 'undefined') {
                try {
                    await INSABuddy.savePrinterSettings(this.printerPaperSize, this.printerFontMode);
                    this.showToast('Paper settings saved', 'success', 1500);
                } catch {
                    this.showToast('Could not save paper settings', 'warning');
                }
            }
        },

        async settingsManualSync() {
            if (typeof window.INSAPOS !== 'undefined' && typeof window.INSAPOS.triggerLocalSync === 'function') {
                try { window.INSAPOS.triggerLocalSync(); } catch (e) {}
            } else if (typeof INSABuddy !== 'undefined') {
                try { await INSABuddy.triggerSync(); } catch (e) {}
            }
            await this.manualSync();
            await this.loadPosSettings();
            this.showToast('Sync triggered', 'info', 1500);
        },

        async settingsScanHardware() {
            if (typeof INSABuddy !== 'undefined') {
                const data = await INSABuddy.scanHardware();
                if (data && data.ok) {
                    const kb = (data.keyboards || []).length;
                    const sc = (data.scanners || []).length;
                    const pr = (data.printers || []).length;
                    this.posSettings.hardware_summary = `${kb} keyboard(s), ${sc} scanner(s), ${pr} printer(s)`;
                    this.showToast('Hardware scan complete', 'success', 1500);
                    return;
                }
            }
            this.showToast('Hardware scan failed', 'warning');
        },

        async saveAllowMinimizeSetting() {
            if (!this.hasNativeBridge || typeof window.INSAPOS === 'undefined') return;
            try {
                const raw = window.INSAPOS.setAllowMinimize(!!this.posSettings.allow_minimize);
                const data = typeof raw === 'string' ? JSON.parse(raw) : raw;
                if (data && data.ok !== false) {
                    this.showToast(this.posSettings.allow_minimize ? 'System bars enabled' : 'Kiosk mode restored', 'success', 1500);
                }
            } catch {
                this.showToast('Could not save display setting', 'warning');
            }
        },

        async settingsTestPrint() {
            if (!this.buddyConnected && !this.hasNativeBridge) {
                this.showToast('Printer requires INSAPuddy or Android app', 'warning');
                return;
            }
            if (typeof INSABuddy === 'undefined') return;
            this.settingsTestPrinting = true;
            try {
                await this.savePosPaperSettings();
                const status = await INSABuddy.getPrinterStatus();
                const parsed = INSABuddy.parsePrinterStatus(status);
                if (!parsed.connected || !parsed.name) {
                    this.showToast('No printer connected — open Printer Setup first', 'warning');
                    return;
                }
                const result = await INSABuddy.testPrint(parsed.type || '', parsed.name || '');
                if (result && (result.ok || result.success)) {
                    this.showToast('Test print sent', 'success');
                } else {
                    this.showToast(INSABuddy.parseApiError(result, 'Test print failed'), 'error');
                }
            } catch {
                this.showToast('Test print failed', 'error');
            } finally {
                this.settingsTestPrinting = false;
            }
        },

        async openPrinterSettings() {
            if (!this.buddyConnected && !this.hasNativeBridge) {
                this.showToast('Printer settings require INSABuddy or the Android app', 'warning');
                return;
            }
            if (typeof INSABuddy !== 'undefined') INSABuddy.detectV2();
            this.printerStep = 1;
            this.printerList = [];
            this.printerSelectedIndex = -1;
            this.printerScanning = false;
            this.printerTesting = false;
            this.printerSaving = false;
            this.printerTestPassed = false;
            this.printerStatusMessage = 'Scan for available printers on this device.';
            this.showPrinterModal = true;
            await this.loadPrinterDefaultStatus();
            await this.loadPrinterLayoutSettings();
        },

        async loadPrinterLayoutSettings() {
            if (typeof INSABuddy === 'undefined') return;
            try {
                const data = await INSABuddy.getPrinterSettings();
                if (data) {
                    this.printerPaperSize = data.paper_size || '57mm';
                    this.printerFontMode = data.font_mode || 'paper_size';
                    const w = data.char_width || 32;
                    const dots = data.dot_width || 384;
                    this.printerLayoutHint = `${w} chars / ${dots} dots per line`;
                }
            } catch {
                this.printerLayoutHint = '32 chars / 384 dots (default)';
            }
        },

        async savePrinterLayoutSettings() {
            if (typeof INSABuddy === 'undefined') return;
            try {
                const data = await INSABuddy.savePrinterSettings(this.printerPaperSize, this.printerFontMode);
                if (data && data.ok) {
                    const w = data.char_width || 32;
                    const dots = data.dot_width || 384;
                    this.printerLayoutHint = `${w} chars / ${dots} dots per line`;
                    this.showToast('Printer layout saved', 'success', 1500);
                }
            } catch {
                this.showToast('Could not save printer layout', 'warning');
            }
        },

        async loadPrinterDefaultStatus() {
            if (typeof INSABuddy === 'undefined') return;
            const data = await INSABuddy.getPrinterStatus();
            this.printerDefault = INSABuddy.parsePrinterStatus(data);
        },

        getSelectedPrinter() {
            const idx = parseInt(this.printerSelectedIndex, 10);
            if (idx < 0 || idx >= this.printerList.length) return null;
            return this.printerList[idx];
        },

        async scanPrinters() {
            if (typeof INSABuddy === 'undefined') return;
            if (typeof INSABuddy !== 'undefined') INSABuddy.detectV2();
            this.printerScanning = true;
            this.printerStatusMessage = 'Scanning for printers...';
            this.printerList = [];
            this.printerSelectedIndex = -1;
            try {
                const data = await INSABuddy.scanPrintersForUi();
                this.printerList = INSABuddy.parsePrinterList(data);
                if (this.printerList.length === 0) {
                    this.printerStatusMessage = 'No printers found. Ensure Bluetooth is on and devices are paired.';
                    this.showToast('No printers found', 'warning');
                } else {
                    this.printerStatusMessage = `Found ${this.printerList.length} printer(s) — select one to continue.`;
                    this.showToast(`Found ${this.printerList.length} printer(s)`, 'success', 2000);
                }
            } catch {
                this.printerStatusMessage = 'Scan failed — check that the local service is running.';
                this.showToast('Printer scan failed', 'error');
            } finally {
                this.printerScanning = false;
            }
        },

        async testSelectedPrinter() {
            const printer = this.getSelectedPrinter();
            if (!printer || typeof INSABuddy === 'undefined') {
                this.showToast('Select a printer first', 'warning');
                return;
            }
            this.printerTesting = true;
            this.printerStatusMessage = `Connecting to ${printer.name}...`;
            try {
                await this.savePrinterLayoutSettings();
                let selectResult = await INSABuddy.selectPrinter(printer.type, printer.name);
                if (INSABuddy.isInitializingResponse(selectResult)) {
                    this.printerStatusMessage = 'Initializing printer…';
                    await new Promise(r => setTimeout(r, 2000));
                    selectResult = await INSABuddy.selectPrinter(printer.type, printer.name);
                }
                const selected = INSABuddy.isSuccessResponse(selectResult)
                    || selectResult?.ok === true
                    || selectResult?.success === true;
                if (!selected) {
                    const selectErr = INSABuddy.parseApiError(selectResult, 'Could not connect to printer');
                    this.printerStatusMessage = selectErr;
                    this.showToast(selectErr, 'error');
                    return;
                }
                this.printerStatusMessage = 'Sending test print...';
                let testResult = await INSABuddy.testPrint(printer.type, printer.name);
                if (INSABuddy.isInitializingResponse(testResult)) {
                    this.printerStatusMessage = 'Initializing printer… retrying test print';
                    await new Promise(r => setTimeout(r, 2000));
                    testResult = await INSABuddy.testPrint(printer.type, printer.name);
                }
                const printed = INSABuddy.isPrintSuccess(testResult);
                if (printed) {
                    this.printerTestPassed = true;
                    this.printerStatusMessage = 'Test print sent! Save as default in the next step.';
                    this.showToast('Test print sent', 'success');
                    this.printerStep = 3;
                    await this.loadPrinterDefaultStatus();
                } else {
                    const testErr = INSABuddy.parseApiError(testResult, 'Test print failed — check paper and connection');
                    this.printerStatusMessage = testErr;
                    this.showToast(testErr, 'error');
                }
            } catch {
                this.printerStatusMessage = 'Test print error — local service may be unavailable.';
                this.showToast('Test print error', 'error');
            } finally {
                this.printerTesting = false;
            }
        },

        async saveDefaultPrinter() {
            const printer = this.getSelectedPrinter();
            if (!printer || typeof INSABuddy === 'undefined') {
                this.showToast('Select a printer first', 'warning');
                return;
            }
            this.printerSaving = true;
            this.printerStatusMessage = `Saving ${printer.name} as default...`;
            try {
                const result = await INSABuddy.selectPrinter(printer.type, printer.name);
                const saved = INSABuddy.isSuccessResponse(result)
                    || result?.ok === true
                    || result?.success === true;
                if (saved) {
                    await this.loadPrinterDefaultStatus();
                    this.printerStatusMessage = `Default printer saved: ${printer.name}`;
                    this.showToast('Default printer saved', 'success');
                } else {
                    const saveErr = INSABuddy.parseApiError(result, 'Could not save default printer');
                    this.printerStatusMessage = saveErr;
                    this.showToast(saveErr, 'error');
                }
            } catch {
                this.printerStatusMessage = 'Save error — local service may be unavailable.';
                this.showToast('Save error', 'error');
            } finally {
                this.printerSaving = false;
            }
        },

        async openIoSettings() {
            if (!this.buddyConnected && !this.hasNativeBridge) {
                this.showToast('I/O settings require INSABuddy or the Android app', 'warning');
                return;
            }
            if (typeof INSABuddy !== 'undefined') INSABuddy.detectV2();
            this.ioMenuView = true;
            this.ioOption = null;
            this.ioStep = 1;
            this.ioApiAvailable = typeof INSABuddy !== 'undefined' && (INSABuddy.hasIoApi() || INSABuddy.isV2());
            this.ioStatusMessage = '';
            this.showIoModal = true;
            await this.loadIoPreferences();
        },

        ioBackToMenu() {
            this.ioMenuView = true;
            this.ioOption = null;
            this.ioStep = 1;
            this.ioStatusMessage = '';
        },

        startIoWizard(option) {
            this.ioMenuView = false;
            this.ioOption = option;
            this.ioStep = option === 'camera' ? 1 : 1;
            this.ioSelectedKeyboardIndex = -1;
            this.ioSelectedMouseIndex = -1;
            this.ioSelectedScannerIndex = -1;
            if (option === 'keyboard') {
                this.ioStatusMessage = 'Scan for keyboards and mice on this device.';
            } else if (option === 'scanner') {
                this.ioStatusMessage = 'Scan for barcode scanners (HID).';
            } else {
                this.ioStatusMessage = 'Toggle camera fallback for product scans.';
            }
            if (option === 'camera') this.applyIoPrefsToSelection();
        },

        applyIoPrefsToSelection(prefs) {
            const p = prefs || {};
            const kbId = p.default_keyboard_id;
            const msId = p.default_mouse_id;
            const scId = p.default_scanner_id;
            if (kbId && this.ioKeyboards.length) {
                const i = this.ioKeyboards.findIndex(d => d.id === String(kbId));
                if (i >= 0) this.ioSelectedKeyboardIndex = i;
            }
            if (msId && this.ioMice.length) {
                const i = this.ioMice.findIndex(d => d.id === String(msId));
                if (i >= 0) this.ioSelectedMouseIndex = i;
            }
            if (scId && this.ioScanners.length) {
                const i = this.ioScanners.findIndex(d => d.id === String(scId));
                if (i >= 0) this.ioSelectedScannerIndex = i;
            }
            if (typeof p.use_camera_for_scan === 'boolean') {
                this.useCameraForScan = p.use_camera_for_scan;
            }
        },

        async loadIoPreferences() {
            if (typeof INSABuddy === 'undefined') return;
            INSABuddy.detectV2();
            try {
                const data = await INSABuddy.getIoStatus();
                if (data?.preferences) {
                    const prefs = INSABuddy.parseIoPreferences(data);
                    this.useCameraForScan = prefs.use_camera_for_scan;
                    localStorage.setItem('insapos_use_camera_for_scan', prefs.use_camera_for_scan ? '1' : '0');
                    this.applyIoPrefsToSelection(prefs);
                }
                this.ioApiAvailable = INSABuddy.hasIoApi() || data?.io_api === true;
            } catch {}
        },

        async scanIoDevices() {
            if (typeof INSABuddy === 'undefined') return;
            this.ioScanning = true;
            this.ioStatusMessage = 'Scanning for devices...';
            try {
                const data = await INSABuddy.scanIoDevices();
                const parsed = INSABuddy.parseIoScan(data);
                this.ioApiAvailable = parsed.ioApi;
                if (this.ioOption === 'keyboard') {
                    this.ioKeyboards = parsed.keyboards;
                    this.ioMice = parsed.mice;
                    const total = parsed.keyboards.length + parsed.mice.length;
                    if (total === 0) {
                        this.ioStatusMessage = data?.message || 'No keyboards or mice found.';
                        this.showToast('No devices found', 'warning');
                    } else {
                        this.ioStatusMessage = `Found ${total} device(s).`;
                        this.applyIoPrefsToSelection(parsed.preferences);
                    }
                } else if (this.ioOption === 'scanner') {
                    this.ioScanners = parsed.scanners;
                    if (parsed.scanners.length === 0) {
                        this.ioStatusMessage = data?.message || 'No scanners found. Pair USB/Bluetooth HID scanner.';
                        this.showToast('No scanners found', 'warning');
                    } else {
                        this.ioStatusMessage = `Found ${parsed.scanners.length} scanner(s).`;
                        this.applyIoPrefsToSelection(parsed.preferences);
                    }
                }
            } catch {
                this.ioStatusMessage = 'Scan failed — check local service.';
                this.showToast('Device scan failed', 'error');
            } finally {
                this.ioScanning = false;
            }
        },

        async testIo() {
            if (typeof INSABuddy === 'undefined') return;
            this.ioTesting = true;
            const type = this.ioOption === 'scanner' ? 'scanner' : (this.ioSelectedMouseIndex >= 0 ? 'mouse' : 'keyboard');
            try {
                const result = await INSABuddy.testIoDevice(type);
                const msg = result?.message || (result?.success ? 'Test OK' : 'Test failed');
                this.ioStatusMessage = msg;
                if (result?.success || result?.ok) {
                    this.showToast(msg, result?.code ? 'success' : 'info', 4000);
                    if (this.ioOption === 'scanner' && result?.code) this.ioStep = 3;
                } else {
                    this.showToast(msg, 'warning', 4000);
                }
            } catch {
                this.ioStatusMessage = 'Test failed.';
                this.showToast('Test failed', 'error');
            } finally {
                this.ioTesting = false;
            }
        },

        async saveIoDefault() {
            if (typeof INSABuddy === 'undefined') return;
            this.ioSaving = true;
            const payload = {};
            if (this.ioOption === 'camera') {
                payload.use_camera_for_scan = !!this.useCameraForScan;
            } else if (this.ioOption === 'keyboard') {
                const kb = this.ioSelectedKeyboardIndex >= 0 ? this.ioKeyboards[this.ioSelectedKeyboardIndex] : null;
                const ms = this.ioSelectedMouseIndex >= 0 ? this.ioMice[this.ioSelectedMouseIndex] : null;
                if (kb) payload.default_keyboard_id = kb.id;
                if (ms) payload.default_mouse_id = ms.id;
            } else if (this.ioOption === 'scanner') {
                const sc = this.ioSelectedScannerIndex >= 0 ? this.ioScanners[this.ioSelectedScannerIndex] : null;
                if (!sc) {
                    this.showToast('Select a scanner first', 'warning');
                    this.ioSaving = false;
                    return;
                }
                payload.default_scanner_id = sc.id;
            }
            try {
                const result = await INSABuddy.saveIoPreferences(payload);
                if (payload.use_camera_for_scan !== undefined) {
                    localStorage.setItem('insapos_use_camera_for_scan', payload.use_camera_for_scan ? '1' : '0');
                }
                if (INSABuddy.isSuccessResponse(result) || result?.saved) {
                    this.ioStatusMessage = 'Preferences saved.';
                    this.showToast('I/O settings saved', 'success');
                    if (this.ioOption === 'camera') this.ioBackToMenu();
                } else {
                    const msg = result?.message || 'Could not save on this device.';
                    this.ioStatusMessage = msg;
                    if (this.ioOption === 'camera' && payload.use_camera_for_scan !== undefined) {
                        localStorage.setItem('insapos_use_camera_for_scan', payload.use_camera_for_scan ? '1' : '0');
                        this.showToast('Saved locally (device prefs need INSAPOS v3)', 'info');
                    } else {
                        this.showToast(msg, 'warning');
                    }
                }
            } catch {
                this.showToast('Save error', 'error');
            } finally {
                this.ioSaving = false;
            }
        },

        async _fetchHidBarcode() {
            try {
                if (typeof INSABuddy !== 'undefined' && (this.buddyConnected || this.hasNativeBridge)) {
                    const data = await INSABuddy.getHidScan();
                    return data?.code || data?.value || null;
                }
                if (this.hasNativeBridge) {
                    const res = await fetch(`http://127.0.0.1:${this._nativeScanPort}/scan/hid`, { signal: AbortSignal.timeout(3000) });
                    const data = await res.json();
                    return data?.code || data?.value || null;
                }
            } catch {}
            return null;
        },

        _waitForHidScan(timeoutMs = 15000) {
            return new Promise((resolve) => {
                const start = Date.now();
                const startScan = this._lastNativeScan;
                const poll = async () => {
                    if (this._lastNativeScan && this._lastNativeScan !== startScan) {
                        resolve(this._lastNativeScan);
                        return;
                    }
                    const code = await this._fetchHidBarcode();
                    if (code) { resolve(code); return; }
                    if (Date.now() - start > timeoutMs) { resolve(null); return; }
                    setTimeout(poll, 400);
                };
                poll();
            });
        },

        async buddyOpenDrawer() { if (!this.buddyConnected) return; await INSABuddy.openDrawer(); },

        async openCashDrawer() {
            if (this.buddyConnected) { this.buddyOpenDrawer(); return; }
            if (this.hasNativeBridge) {
                try { await fetch(`http://127.0.0.1:${this._nativeScanPort}/drawer/open`, { method: 'POST', signal: AbortSignal.timeout(5000) }); } catch {}
                return;
            }
        },

        async _nativeScanAsync() {
            try {
                const res = await fetch(`http://127.0.0.1:${this._nativeScanPort}/scan`, { signal: AbortSignal.timeout(30000) });
                const data = await res.json();
                if (data.ok && data.code) return data.code;
            } catch {}
            return null;
        },

        async scanProduct() {
            if (this._scanning) return;
            const useCamera = this.useCameraForScan !== false;
            if (this.buddyConnected || this.hasNativeBridge) {
                this._scanning = true;
                try {
                    let value = await this._fetchHidBarcode();
                    if (!value) value = await this._waitForHidScan(800);
                    if (!value && useCamera) {
                        this.showToast('Scanning with camera...', 'info', 2000);
                        if (this.buddyConnected) {
                            const r = await INSABuddy.scan();
                            if (r && (r.success || r.ok) && (r.value || r.code)) value = r.value || r.code;
                        } else if (this.hasNativeBridge) {
                            value = await this._nativeScanAsync();
                        }
                    } else if (!value && !useCamera) {
                        this.showToast('Use your barcode scanner (camera is off)', 'info', 3000);
                        value = await this._waitForHidScan(20000);
                    }
                    if (value) { this.handleBarcodeScan(value); }
                    else { this.showToast(useCamera ? 'No barcode detected. Try again.' : 'No scan received from scanner.', 'warning'); }
                } catch { this.showToast('Scan failed.', 'error'); }
                finally { this._scanning = false; }
            } else if (useCamera) {
                this.openCameraScanner('product');
            } else {
                this.showToast('Enable camera in I/O Settings or use the Android app', 'warning');
            }
        },

        async openCameraScanner(purpose) {
            if (this.showCameraScanner) return;
            this._cameraPurpose = purpose || 'product';
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } } });
                this._cameraStream = stream;
                this.showCameraScanner = true;
                this.$nextTick(() => {
                    const video = document.getElementById('cameraScanVideo');
                    if (video) { video.srcObject = stream; video.play(); }
                    this._startCameraDetection();
                });
            } catch (e) {
                console.error('[camera]', e);
                this.showToast('Camera not available. Check permissions.', 'error');
            }
        },

        _startCameraDetection() {
            const video = document.getElementById('cameraScanVideo');
            if (!video) return;
            const hasBarcodeDetector = typeof BarcodeDetector !== 'undefined';
            if (!hasBarcodeDetector) {
                this.showToast('Camera scanning ready — point at a barcode', 'info', 3000);
            }
            this._cameraScanInterval = setInterval(async () => {
                if (!this.showCameraScanner || !video.videoWidth) return;
                try {
                    if (hasBarcodeDetector) {
                        const detector = new BarcodeDetector({ formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'code_93', 'codabar', 'itf', 'qr_code', 'data_matrix'] });
                        const barcodes = await detector.detect(video);
                        if (barcodes.length > 0) {
                            const val = barcodes[0].rawValue;
                            if (val) { this.closeCameraScanner(); this._handleCameraScanResult(val); return; }
                        }
                    }
                } catch {}
            }, 400);
        },

        _handleCameraScanResult(value) {
            if (this._cameraPurpose === 'rewards') {
                this.rewardsCardInput = value;
                this.lookupRewardsCustomer();
            } else {
                this.handleBarcodeScan(value);
            }
        },

        closeCameraScanner() {
            this.showCameraScanner = false;
            if (this._cameraScanInterval) { clearInterval(this._cameraScanInterval); this._cameraScanInterval = null; }
            if (this._cameraStream) { this._cameraStream.getTracks().forEach(t => t.stop()); this._cameraStream = null; }
            const video = document.getElementById('cameraScanVideo');
            if (video) video.srcObject = null;
        },

        async scanRewardsCard() {
            if (this._scanning) return;
            if (this.buddyConnected || this.hasNativeBridge) {
                this._scanning = true;
                this.showToast('Scanning customer card...', 'info', 2000);
                try {
                    let value = null;
                    if (this.buddyConnected) {
                        const r = await INSABuddy.scan();
                        if (r && r.success && r.value) value = r.value;
                    } else if (this.hasNativeBridge) {
                        value = await this._nativeScanAsync();
                    }
                    if (value) { this.rewardsCardInput = value; await this.lookupRewardsCustomer(); }
                    else { this.showToast('No code detected. Try again or type manually.', 'warning'); }
                } catch { this.showToast('Scan failed.', 'error'); }
                finally { this._scanning = false; }
            } else {
                this.openCameraScanner('rewards');
            }
        },

        async lookupRewardsCustomer() {
            const q = (this.rewardsCardInput || '').trim();
            if (!q) return;
            this.rewardsResults = [];
            this.rewardsNoMatch = false;
            try {
                const res = await fetch('/api/pos/customer/quick-lookup', { method: 'POST', headers: this.csrfHeader(), body: JSON.stringify({ query: q }) });
                if (!res.ok) { this.rewardsNoMatch = true; return; }
                const data = await res.json();
                const customers = (data.customers || []).map(c => ({ id: c.id, uuid: c.uuid, name: c.full_name || c.name || ((c.first_name || '') + ' ' + (c.last_name || '')).trim(), phone: c.phone, email: c.email, card_number: c.card_number, loyalty_points: c.loyalty_points }));
                if (customers.length > 0) {
                    this.rewardsResults = customers;
                    if (customers.length === 1) this.selectRewardsCustomer(customers[0]);
                } else { this.rewardsNoMatch = true; }
            } catch { this.showToast('Could not look up customer.', 'error'); }
        },

        selectRewardsCustomer(c) {
            this.selectedCustomer = c;
            this.customerSearch = c.name;
            this.rewardsCardInput = '';
            this.rewardsResults = [];
            this.rewardsNoMatch = false;
            this.showRewardsModal = false;
            this.showToast('Customer tagged: ' + c.name, 'success');
        },

        canUsePrinter() {
            return this.hasNativeBridge || this.buddyConnected;
        },

        /** Prefer server sale_number (e.g. S20260528140256VWFS); never show raw local_id in UI. */
        saleDisplayNumber(sale) {
            if (!sale) return '—';
            if (sale.sale_number) return sale.sale_number;
            if (sale.offline || sale.status === 'pending' || sale.sync_status === 'pending') {
                return 'Pending sync';
            }
            return '—';
        },

        saleRowKey(sale) {
            return sale?.id || sale?.sale_number || sale?.local_id || '';
        },

        receiptPrintPayloadFromData(data) {
            const items = (data.items || []).map(i => ({
                name: i.product_name || i.name,
                qty: i.qty,
                price: parseFloat(i.price),
                discount: parseFloat(i.discount || 0),
            }));
            const subtotal = data.subtotal != null
                ? parseFloat(data.subtotal)
                : items.reduce((s, i) => s + i.qty * i.price, 0);
            const discount = data.discount_total != null
                ? parseFloat(data.discount_total)
                : parseFloat(data.discount || 0) + items.reduce((s, i) => s + (i.discount || 0), 0);
            return {
                storeName: data.store_name || '{{ $brandName }}',
                branchName: data.branch_name || '{{ auth()->user()->branch?->name ?? "" }}',
                saleNumber: data.sale_number || '',
                date: data.created_at ? new Date(data.created_at).toLocaleString() : new Date().toLocaleString(),
                cashier: data.cashier || '{{ auth()->user()->name }}',
                items,
                subtotal,
                discount,
                total: parseFloat(data.total || 0),
                paymentMethod: data.payment_method || 'cash',
                amountTendered: parseFloat(data.amount_tendered || 0),
                change: parseFloat(data.change_due || 0),
                customer: data.customer || null,
            };
        },

        async sendReceiptToPrinter(payload, options = {}) {
            if (!this.canUsePrinter()) {
                this.showToast('Printer service unavailable', 'error');
                return false;
            }
            const receiptText = payload.text || null;
            const nativeFirst = this.hasNativeBridge && typeof window.INSAPOS !== 'undefined'
                && typeof window.INSAPOS.printReceipt === 'function';
            try {
                if (nativeFirst) {
                    let text = receiptText;
                    if (!text) {
                        if (typeof INSABuddy !== 'undefined') {
                            INSABuddy.detectV2();
                            const settings = INSABuddy._printerLayoutCache
                                || INSABuddy.resolvePrinterLayout(this.printerPaperSize, this.printerFontMode);
                            text = INSABuddy.buildReceiptText(payload, settings);
                        } else {
                            text = this.formatNativeReceiptText(payload);
                        }
                    }
                    const result = (typeof INSABuddy !== 'undefined' && INSABuddy.printReceiptNative)
                        ? INSABuddy.printReceiptNative(text, options.maxAttempts || 3)
                        : (() => {
                            const raw = window.INSAPOS.printReceipt(JSON.stringify({ text }));
                            return typeof raw === 'string' ? JSON.parse(raw) : raw;
                        })();
                    if (result && (result.ok || result.printed)) {
                        if (!options.silent) this.showToast('Receipt sent to printer', 'success', 2000);
                        return true;
                    }
                    const err = (result && (result.error || result.print_error))
                        || 'Receipt print failed';
                    console.warn('[pos] native print failed:', err, result);
                    if (!options.silent) this.showToast(err, 'error');
                    return false;
                }
                if (typeof INSABuddy !== 'undefined') {
                    INSABuddy.detectV2();
                    const result = await INSABuddy.printReceipt(payload);
                    if (!INSABuddy.isPrintSuccess(result)) {
                        this.showToast(INSABuddy.parseApiError(result, 'Receipt print failed'), 'error');
                        return false;
                    }
                } else {
                    this.showToast('Printer service unavailable', 'error');
                    return false;
                }
                if (!options.silent) this.showToast('Receipt sent to printer', 'success', 2000);
                return true;
            } catch (e) {
                console.warn('[pos] sendReceiptToPrinter failed:', e);
                if (!options.silent) this.showToast('Receipt print failed — local service unavailable', 'error');
                return false;
            }
        },

        formatNativeReceiptText(payload) {
            const lines = [];
            lines.push(payload.storeName || 'INSA POS');
            if (payload.branchName) lines.push(payload.branchName);
            lines.push('--------------------------------');
            if (payload.saleNumber) lines.push('Sale #: ' + payload.saleNumber);
            lines.push('Date: ' + (payload.date || new Date().toLocaleString()));
            lines.push('Cashier: ' + (payload.cashier || ''));
            lines.push('--------------------------------');
            (payload.items || []).forEach((item) => {
                lines.push(String(item.name).substring(0, 20) + ' x' + item.qty + ' ' + (item.qty * item.price).toFixed(2));
            });
            lines.push('--------------------------------');
            lines.push('TOTAL: ' + parseFloat(payload.total || 0).toFixed(2));
            lines.push('Payment: ' + (payload.paymentMethod || 'cash'));
            if (payload.amountTendered) {
                lines.push('Tendered: ' + parseFloat(payload.amountTendered).toFixed(2));
                lines.push('Change: ' + parseFloat(payload.change || 0).toFixed(2));
            }
            lines.push('');
            lines.push('Thank you for your purchase!');
            return lines.join('\n');
        },

        createLocalSaleWithTimeout(payloadStr, timeoutMs = 25000) {
            return new Promise((resolve, reject) => {
                window.__insaposSalePending = window.__insaposSalePending || {};
                let raw;
                try {
                    raw = window.INSAPOS.createLocalSale(payloadStr);
                } catch (e) {
                    reject(e);
                    return;
                }
                let init;
                try {
                    init = typeof raw === 'string' ? JSON.parse(raw) : raw;
                } catch (e) {
                    reject(e);
                    return;
                }
                if (!init.pending || !init.request_id) {
                    resolve(init);
                    return;
                }
                const requestId = init.request_id;
                const timeout = setTimeout(() => {
                    delete window.__insaposSalePending[requestId];
                    reject(new Error('Sale timed out after ' + Math.round(timeoutMs / 1000) + 's'));
                }, timeoutMs);
                window.__insaposSalePending[requestId] = { resolve, reject, timeout };
            });
        },

        async printReceiptAfterSale(saleResult, txData, receiptData) {
            if (!this.canUsePrinter() || !this.autoPrintReceipt) return;
            const alreadyPrinted = saleResult && (saleResult.already_printed === true || saleResult.printed === true);
            if (alreadyPrinted) {
                console.info('[pos] receipt auto-printed by native sale handler');
                return;
            }
            const printOpts = { silent: false, maxAttempts: 3 };
            const receiptObj = saleResult && saleResult.receipt;
            if (receiptObj && receiptObj.text) {
                await this.sendReceiptToPrinter({ text: receiptObj.text }, printOpts);
                return;
            }
            if (receiptData && receiptData.receipt_text) {
                await this.sendReceiptToPrinter({ text: receiptData.receipt_text }, printOpts);
                return;
            }
            const payload = this.receiptPrintPayloadFromData({
                sale_number: (saleResult && saleResult.sale && saleResult.sale.sale_number) || null,
                local_id: txData.local_id,
                items: txData.items,
                subtotal: txData.subtotal,
                discount_total: txData.discount_total,
                total: txData.total,
                payment_method: txData.payment_method,
                amount_tendered: txData.amount_tendered,
                change_due: txData.change_due,
                customer: this.selectedCustomer?.name || null,
                store_name: receiptData.store_name,
                branch_name: receiptData.branch_name,
                cashier: receiptData.cashier,
                created_at: txData.created_at,
            });
            await this.sendReceiptToPrinter(payload, printOpts);
        },

        async buddyPrintReceipt() {
            if (!this.canUsePrinter() || !this.lastSale) return;
            const payload = this.receiptPrintPayloadFromData({
                sale_number: this.lastSale.sale_number,
                local_id: this.lastSale.local_id,
                items: this.lastSale._cart || this.cart,
                subtotal: this.cartSubtotal,
                discount_total: this.cartDiscount,
                total: this.lastSale.total,
                payment_method: this.lastSale.payment_method || this.paymentMethod,
                amount_tendered: this.lastSale.amount_tendered,
                change_due: this.lastSale.change_due,
                customer: this.selectedCustomer?.name || null,
            });
            await this.sendReceiptToPrinter(payload);
        },

        async buildReceiptDataForSale(sale) {
            const db = window.INSADB;
            const localId = sale.local_id;
            if (db && localId) {
                try {
                    const stored = await db.receipts.getByTxId(localId);
                    if (stored) return stored;
                } catch {}
                try {
                    const tx = await db.transactions.getByLocalId(localId);
                    if (tx && tx.items) {
                        return {
                            sale_number: sale.sale_number,
                            local_id: localId,
                            created_at: tx.created_at || sale.created_at,
                            items: tx.items,
                            subtotal: tx.subtotal,
                            discount_total: tx.discount_total,
                            total: tx.total,
                            payment_method: tx.payment_method,
                            amount_tendered: tx.amount_tendered,
                            change_due: tx.change_due,
                        };
                    }
                } catch {}
            }
            if (sale.items && sale.items.length) {
                return sale;
            }
            if (sale.id) {
                try {
                    const res = await fetch('/api/pos/sales/' + sale.id + '/receipt', { headers: this.csrfHeader() });
                    if (!res.ok) return null;
                    const data = await res.json();
                    if (data.success && data.receipt) return data.receipt;
                } catch {}
            }
            return null;
        },

        async reprintSale(sale) {
            if (!this.canUsePrinter()) {
                this.showToast('Printer requires INSABuddy or the Android app', 'warning');
                return;
            }
            const key = this.saleRowKey(sale);
            this.reprintingSaleKey = key;
            try {
                const raw = await this.buildReceiptDataForSale(sale);
                if (!raw || !(raw.items || []).length) {
                    this.showToast('Receipt data not available for this sale', 'error');
                    return;
                }
                await this.sendReceiptToPrinter(this.receiptPrintPayloadFromData(raw));
            } finally {
                this.reprintingSaleKey = null;
            }
        },

        async searchCustomers() {
            const q = this.customerSearch.trim();
            if (q.length < 2) { this.customerResults = []; return; }
            const db = window.INSADB;
            if (db) { this.customerResults = await db.customers.search(q); if (this.customerResults.length > 0) { this.showCustomerDropdown = true; return; } }
            try {
                const res = await fetch('/api/pos/customer/quick-lookup', { method: 'POST', headers: this.csrfHeader(), body: JSON.stringify({ query: q }) });
                const data = await res.json();
                this.customerResults = (data.customers || []).map(c => ({ id: c.id, uuid: c.uuid, name: c.full_name || c.name || ((c.first_name || '') + ' ' + (c.last_name || '')).trim(), phone: c.phone, email: c.email, card_number: c.card_number }));
                if (this.customerResults.length > 0) this.showCustomerDropdown = true;
            } catch {}
        },

        selectCustomer(c) { this.selectedCustomer = c; this.customerSearch = c.name; this.showCustomerDropdown = false; this.customerResults = []; },

        openItemDiscount(idx) {
            this.discountEditIndex = idx; this.discountEditItem = this.cart[idx];
            this.discountValue = this.cart[idx].discount || 0; this.discountType = 'amount'; this.showItemDiscountModal = true;
        },

        applyItemDiscount() {
            if (this.discountEditIndex < 0 || !this.cart[this.discountEditIndex]) return;
            const item = this.cart[this.discountEditIndex]; const lineTotal = item.qty * item.price;
            item.discount = this.discountType === 'percent'
                ? Math.min(lineTotal, (lineTotal * (this.discountValue || 0)) / 100)
                : Math.min(lineTotal, Math.max(0, this.discountValue || 0));
            item.discount = parseFloat(item.discount.toFixed(2)); this.showItemDiscountModal = false;
        },

        applyOrderDiscount() {
            const subtotal = this.cartSubtotal;
            const itemDisc = this.cart.reduce((s, i) => s + (i.discount || 0), 0);
            const max = subtotal - itemDisc;
            this.orderDiscountApplied = this.orderDiscountType === 'percent'
                ? Math.min(max, (subtotal * (this.orderDiscountValue || 0)) / 100)
                : Math.min(max, Math.max(0, this.orderDiscountValue || 0));
            this.orderDiscountApplied = parseFloat(this.orderDiscountApplied.toFixed(2)); this.showOrderDiscountModal = false;
        },

        async manualSync() {
            if (!window.SyncEngine) return;
            this.showToast('Downloading store data…', 'info', 2000);
            this.storeDownload.active = true;
            this.storeDownload.percent = 0;
            await SyncEngine.downloadAll({ force: true, silent: false });
            await this.refreshProductsFromDB();
        },

        async resolveConflict(choice) {
            this.showConflictModal = false; const db = window.INSADB;
            if (!db || !this.conflictLocalId) return;
            if (choice === 'server') {
                const tx = await db.transactions.getByLocalId(this.conflictLocalId);
                if (tx && this.conflictItems) {
                    for (const c of this.conflictItems) { const item = tx.items.find(i => i.product_id === c.product_id); if (item && c.field === 'price') item.price = parseFloat(c.server_value); }
                    tx.subtotal = tx.items.reduce((s, i) => s + (i.qty * i.price), 0);
                    tx.total = tx.subtotal - tx.items.reduce((s, i) => s + (i.discount || 0), 0);
                    tx.status = 'pending'; await db.transactions.add(tx);
                }
            } else {
                const tx = await db.transactions.getByLocalId(this.conflictLocalId);
                if (tx) { tx.status = 'pending'; tx.force_local = true; await db.transactions.add(tx); }
            }
            this.conflictItems = []; this.conflictLocalId = null; this.showToast('Conflict resolved — will retry sync', 'success');
        },

        csrfHeader() { return { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', 'Accept': 'application/json' }; },

        mapLocalShift(shift) {
            const rowId = shift.id;
            const serverId = shift.server_id || 0;
            return {
                id: serverId > 0 ? serverId : rowId,
                local_shift_id: rowId,
                server_shift_id: serverId > 0 ? serverId : null,
                local_id: shift.local_id,
                opening_cash: shift.opening_cash,
                opened_at: shift.opened_at || new Date().toISOString(),
                total_sales: shift.total_sales,
                transaction_count: shift.transaction_count,
            };
        },

        shiftIdForNativeSale() {
            if (!this.activeShift) return 0;
            return this.activeShift.local_shift_id || this.activeShift.id || 0;
        },

        applyShiftTotals(shift) {
            if (!shift) return;
            const total = parseFloat(shift.total_sales);
            if (!isNaN(total)) this.shiftSalesTotal = total;
            if (shift.transaction_count != null) this.shiftTransactionCount = shift.transaction_count;
        },

        async refreshShiftSalesTotal() {
            if (!this.activeShift) {
                this.shiftSalesTotal = 0;
                this.shiftTransactionCount = 0;
                return;
            }
            if (this.useNativeEngine() && typeof window.INSAPOS.getShiftSalesTotal === 'function') {
                try {
                    const raw = window.INSAPOS.getShiftSalesTotal();
                    const data = typeof raw === 'string' ? JSON.parse(raw) : raw;
                    if (data.ok) {
                        this.shiftSalesTotal = parseFloat(data.total_sales || 0);
                        this.shiftTransactionCount = data.transaction_count || 0;
                    }
                } catch (e) { console.warn('[pos] native shift sales total failed:', e); }
            }
        },

        async loadShift() {
            if (this.useNativeEngine() && typeof window.INSAPOS.getLocalShiftStatus === 'function') {
                try {
                    const raw = window.INSAPOS.getLocalShiftStatus();
                    const data = typeof raw === 'string' ? JSON.parse(raw) : raw;
                    if (data.ok && data.shift && data.shift !== null) {
                        this.activeShift = this.mapLocalShift(data.shift);
                        this.applyShiftTotals(data.shift);
                    }
                } catch (e) { console.warn('[pos] local shift status failed:', e); }
            }
            try {
                const res = await fetch('/api/pos/shift/current');
                const data = await res.json();
                if (data.success && data.shift) {
                    this.activeShift = data.shift;
                } else if (!this.activeShift) {
                    this.activeShift = null;
                }
            } catch {
                if (!this.activeShift) this.activeShift = null;
            }
            if (this.activeShift) await this.refreshShiftSalesTotal();
            else {
                this.shiftSalesTotal = 0;
                this.shiftTransactionCount = 0;
            }
        },

        async loadProductsFromCache() {
            const db = window.INSADB;
            if (!db) return false;
            try {
                const cachedCats = await db.categories.getAll();
                if (cachedCats.length > 0) this.categories = cachedCats;
                let cached = await db.products.getAll();
                if (db.productStock && this.config.branchId) {
                    cached = await db.productStock.mergeIntoProducts(cached, this.config.branchId);
                }
                if (cached.length > 0) {
                    this.products = cached;
                    this.invalidateSearchCache();
                    this.filterProducts();
                    this.productsLoading = false;
                    return true;
                }
            } catch (e) {
                console.warn('[pos] cache read failed:', e);
            }
            return false;
        },

        async loadProducts() {
            if (this.isStorageCatalog()) {
                this.productsLoading = false;
                return;
            }
            const db = window.INSADB;
            const useLocalOnly = this.posReady && this.products.length > 0;
            if (useLocalOnly) {
                this.productsLoading = false;
                if (db) await this.refreshProductsFromDB();
                this.filterProducts();
                return;
            }
            const hadCache = this.products.length > 0;
            if (!hadCache) this.productsLoading = true;
            else {
                this.productsLoading = false;
                if (window.SyncEngine && await SyncEngine.isCacheReady()) {
                    await this.refreshProductsFromDB();
                    this.filterProducts();
                    return;
                }
                this._refreshProductsFromNetwork().catch((e) => {
                    console.warn('[pos] background product refresh failed:', e);
                });
                return;
            }
            try {
                await this._refreshProductsFromNetwork();
            } catch (e) {
                console.warn('[pos] loadProducts fetch failed, using cache:', e);
                if (db) {
                    const cached = await db.products.getAll();
                    if (cached.length > 0) {
                        this.products = cached;
                        this.invalidateSearchCache();
                        this.rebuildProductIndex();
                        this.showToast('Using cached products (offline)', 'warning');
                    }
                    const cachedCats = await db.categories.getAll();
                    if (cachedCats.length > 0) this.categories = cachedCats;
                }
                this.filterProducts();
            } finally {
                this.productsLoading = false;
            }
        },

        async _refreshProductsFromNetwork() {
            const db = window.INSADB;
            const res = await fetch('/api/pos/products/all?branch_id=' + (this.config.branchId || ''));
            const data = await res.json();
            const rawProducts = data.products || [];
            const rawCategories = data.categories || [];
            if (db && rawProducts.length > 0) {
                db.products.bulkPut(rawProducts).catch(() => {});
                if (db.productStock && this.config.branchId) {
                    db.productStock.bulkPut(rawProducts, this.config.branchId).catch(() => {});
                }
            }
            if (db && rawCategories.length > 0) {
                db.categories.bulkPut(rawCategories).catch(() => {});
            }
            if (rawProducts.length > 0) { this.products = rawProducts; this.invalidateSearchCache(); this.rebuildProductIndex(); }
            if (rawCategories.length > 0) this.categories = rawCategories;
            this.filterProducts();
        },

        async refreshProductsFromDB() {
            if (this.isStorageCatalog()) {
                await this.refreshNativeProductCount();
                this.loadNativeCategories();
                this.warnIfNativeCategoriesMissing();
                this._nativeGridOffset = 0;
                this.filterProducts();
                return;
            }
            const db = window.INSADB;
            if (!db) return;
            let cached = await db.products.getAll();
            if (db.productStock && this.config.branchId) {
                cached = await db.productStock.mergeIntoProducts(cached, this.config.branchId);
            }
            if (cached.length > 0) { this.products = cached; this.invalidateSearchCache(); this.rebuildProductIndex(); this.filterProducts(); }
        },

        loadMoreGrid() {
            if (this.isStorageCatalog()) {
                const q = this.searchQuery.trim();
                const offset = this.filteredProducts.length;
                const page = this.fetchNativeProductsPage(
                    q.length >= 3 ? q : '',
                    offset,
                    48,
                    this.selectedCategory
                );
                if (page.items.length) {
                    this.filteredProducts = this.filteredProducts.concat(page.items);
                    this.filteredProductsTotal = page.total;
                    this.gridCanLoadMore = page.hasMore;
                } else {
                    this.gridCanLoadMore = false;
                }
                return;
            }
            this.gridDisplayLimit += 48;
            const cacheKey = 'c|' + this.selectedCategory + '|' + this.searchQuery.trim() + '|' + this.gridDisplayLimit;
            delete this._searchCache[cacheKey];
            this.filterProducts();
        },

        filterProducts() {
            if (this.isStorageCatalog()) {
                this.filterProductsStorage();
                return;
            }
            if (this.posMode === 'retail') {
                if (this.filteredProducts.length || this.filteredProductsTotal) {
                    this.filteredProducts = [];
                    this.filteredProductsTotal = 0;
                }
                this.catalogNeedsCategory = false;
                this.gridCanLoadMore = false;
                return;
            }
            const SEARCH_LIMIT = 80;
            const q = this.searchQuery.trim();
            if (!q && !this.selectedCategory && this.products.length > this.LARGE_CATALOG_THRESHOLD) {
                this.catalogNeedsCategory = true;
                this.filteredProducts = [];
                this.filteredProductsTotal = 0;
                this.gridCanLoadMore = false;
                return;
            }
            this.catalogNeedsCategory = false;
            const cacheKey = 'c|' + this.selectedCategory + '|' + q + '|' + this.gridDisplayLimit;
            if (this._searchCache[cacheKey]) {
                const cached = this._searchCache[cacheKey];
                this.filteredProductsTotal = cached.total;
                this.filteredProducts = cached.items;
                this.gridCanLoadMore = cached.total > cached.items.length;
                return;
            }
            let result = this.products;
            if (this.selectedCategory) result = result.filter(p => p.category_id == this.selectedCategory);
            if (q.length >= 3) {
                const ql = q.toLowerCase();
                const filtered = [];
                for (let i = 0; i < result.length && filtered.length < SEARCH_LIMIT; i++) {
                    const p = result[i];
                    if (p.name.toLowerCase().includes(ql) ||
                        (p.sku && p.sku.toLowerCase().includes(ql)) ||
                        (p.barcode && p.barcode.includes(q))) {
                        filtered.push(p);
                    }
                }
                result = filtered;
            } else if (q.length > 0 && q.length < 3) {
                this.filteredProducts = [];
                this.filteredProductsTotal = 0;
                this.gridCanLoadMore = false;
                return;
            }
            const total = result.length;
            const limit = this.gridDisplayLimit;
            const items = total > limit ? result.slice(0, limit) : result;
            this.rememberSearchCache(cacheKey, { total, items });
            this.filteredProductsTotal = total;
            this.filteredProducts = items;
            this.gridCanLoadMore = total > items.length;
        },

        filterProductsStorage() {
            if (this.posMode === 'retail') {
                if (this.filteredProducts.length || this.filteredProductsTotal) {
                    this.filteredProducts = [];
                    this.filteredProductsTotal = 0;
                }
                this.catalogNeedsCategory = false;
                this.gridCanLoadMore = false;
                return;
            }
            const q = this.searchQuery.trim();
            const catalogSize = this.productCount || 0;
            if (!q && !this.selectedCategory && catalogSize > this.LARGE_CATALOG_THRESHOLD) {
                this.catalogNeedsCategory = true;
                this.filteredProducts = [];
                this.filteredProductsTotal = 0;
                this.gridCanLoadMore = false;
                return;
            }
            this.catalogNeedsCategory = false;
            const cacheKey = 's|' + this.selectedCategory + '|' + q + '|' + this.gridDisplayLimit;
            if (this._searchCache[cacheKey]) {
                const cached = this._searchCache[cacheKey];
                this.filteredProductsTotal = cached.total;
                this.filteredProducts = cached.items;
                this.gridCanLoadMore = cached.hasMore;
                return;
            }
            const limit = q.length >= 3 ? 80 : this.gridDisplayLimit;
            const page = this.fetchNativeProductsPage(
                q.length >= 3 ? q : '',
                0,
                limit,
                this.selectedCategory
            );
            this.products = page.items;
            this.rememberSearchCache(cacheKey, {
                total: page.total,
                items: page.items,
                hasMore: page.hasMore,
            });
            this.filteredProductsTotal = page.total;
            this.filteredProducts = page.items;
            this.gridCanLoadMore = page.hasMore;
            this.productsLoading = false;
        },

        async loadRecentSales() {
            this.recentSales = [];
            try {
                const db = window.INSADB;
                if (db) {
                    try {
                        const local = await db.transactions.getAll();
                        if (local && local.length) this.recentSales = local.sort((a, b) => new Date(b.created_at) - new Date(a.created_at)).slice(0, 20);
                    } catch {}
                }
            } catch {}
            try {
                const res = await fetch('/api/pos/sales/recent?limit=20', { headers: this.csrfHeader() });
                if (!res.ok) return;
                const data = await res.json();
                if (data.success && data.sales && data.sales.length > 0) {
                    const serverSales = data.sales.map(s => ({ ...s, total: parseFloat(s.total || 0), status: s.status || 'completed' }));
                    if (this.recentSales.length === 0) { this.recentSales = serverSales; return; }
                    const localIds = new Set(this.recentSales.map(s => s.local_id).filter(Boolean));
                    const merged = [...this.recentSales];
                    for (const s of serverSales) { if (!s.local_id || !localIds.has(s.local_id)) merged.push(s); }
                    this.recentSales = merged.sort((a, b) => new Date(b.created_at) - new Date(a.created_at)).slice(0, 25);
                }
            } catch {}
        },

        addToCart(product, force = false) {
            if (!force && product.stock <= 0) { this.showToast('Out of stock: ' + product.name, 'warning'); return false; }
            const existing = this.cart.find(i => i.product_id === product.id);
            if (existing) {
                if (!force && product.stock > 0 && existing.qty >= product.stock) { this.showToast('Not enough stock. Available: ' + product.stock, 'warning'); return false; }
                existing.qty++;
            } else {
                this.cart.push({ product_id: product.id, product_name: product.name, sku: product.sku, barcode: product.barcode, price: parseFloat(product.price), qty: 1, discount: 0 });
            }
            if (force && product.stock <= 0) this.showToast('Warning: ' + product.name + ' shows 0 stock in system', 'warning', 2500);
            this.scheduleCustomerDisplaySync();
            return true;
        },

        changeQty(idx, delta) {
            const item = this.cart[idx]; const newQty = item.qty + delta;
            if (newQty <= 0) { this.cart.splice(idx, 1); }
            else {
                let stockCap = null;
                if (this.isStorageCatalog()) {
                    const cached = this.findProductInLru(item.product_id);
                    if (cached) stockCap = cached.stock;
                } else {
                    const product = this.products.find(p => p.id === item.product_id);
                    if (product) stockCap = product.stock;
                }
                if (stockCap != null && stockCap > 0 && newQty > stockCap) {
                    this.showToast('Not enough stock. Available: ' + stockCap, 'warning');
                    return;
                }
                item.qty = newQty;
            }
            this.scheduleCustomerDisplaySync();
        },

        removeItem(idx) { this.cart.splice(idx, 1); this.scheduleCustomerDisplaySync(); },
        clearCart() { this.cart = []; this.orderDiscountApplied = 0; this.orderDiscountValue = 0; this.selectedCustomer = null; this.customerSearch = ''; this.scheduleCustomerDisplaySync(); },

        goToCheckout() { if (this.cart.length === 0) return; this.amountTendered = 0; this.changeAmount = 0; this.paymentMethod = 'cash'; this.paymentRef = ''; this.screen = 'checkout'; this.scheduleCustomerDisplaySync(); },
        calculateChange() { this.changeAmount = (this.amountTendered || 0) - this.cartTotal; },

        async completeSale() {
            if (!this.canProceed) return;
            if (!this.activeShift) { this.showToast('No active shift. Please open a shift first.', 'error'); this.screen = 'pos'; return; }
            const db = window.INSADB;
            const nativeEngine = this.useNativeEngine();
            const tendered = this.paymentMethod === 'cash' ? this.amountTendered : this.cartTotal;
            const localId = db ? db.generateUUID() : crypto.randomUUID ? crypto.randomUUID() : Date.now().toString();
            const txData = {
                local_id: localId, branch_id: this.config.branchId, shift_id: nativeEngine ? this.shiftIdForNativeSale() : this.activeShift.id, cashier_id: this.config.cashierId,
                member_id: this.selectedCustomer?.id || null, payment_method: this.paymentMethod, payment_ref: this.paymentRef || null,
                amount_tendered: tendered, items: JSON.parse(JSON.stringify(this.cart)),
                subtotal: this.cartSubtotal, discount_total: this.cartDiscount, order_discount: this.orderDiscountApplied,
                total: this.cartTotal, change_due: Math.max(0, tendered - this.cartTotal), status: 'pending', created_at: new Date().toISOString(),
            };
            const receiptData = {
                local_tx_id: localId, sale_number: null, store_name: '{{ $brandName }}', branch_name: '{{ auth()->user()->branch?->name ?? "" }}',
                cashier: '{{ auth()->user()->name }}', items: txData.items, subtotal: txData.subtotal, discount: txData.discount_total,
                total: txData.total, payment_method: txData.payment_method, amount_tendered: txData.amount_tendered,
                change_due: txData.change_due, customer: this.selectedCustomer?.name || null,
            };
            let serverSale = null;
            let localSaleOk = false;
            let nativeSaleResult = null;

            const queueBackgroundSync = () => {
                if (typeof window.INSAPOS !== 'undefined' && typeof window.INSAPOS.triggerLocalSync === 'function') {
                    try { window.INSAPOS.triggerLocalSync(); } catch (e) { console.warn('[pos] background sync:', e); }
                } else if (window.SyncEngine) {
                    SyncEngine.pushTransactions().catch(() => {});
                }
            };

            if (nativeEngine) {
                this.saleProcessing = true;
                try {
                    const payload = JSON.stringify({
                        local_id: localId,
                        branch_id: txData.branch_id,
                        shift_id: txData.shift_id,
                        cashier_id: txData.cashier_id,
                        member_id: txData.member_id,
                        payment_method: txData.payment_method,
                        amount_tendered: txData.amount_tendered,
                        items: txData.items,
                        subtotal: txData.subtotal,
                        discount_total: txData.discount_total,
                        order_discount: txData.order_discount,
                        total: txData.total,
                        change_due: txData.change_due,
                        cashier_name: '{{ auth()->user()->name }}',
                        branch_name: '{{ auth()->user()->branch?->name ?? "" }}',
                        store_name: '{{ $brandName }}',
                        created_at: txData.created_at,
                        auto_print: this.autoPrintReceipt !== false,
                        async: true,
                    });
                    const data = await this.createLocalSaleWithTimeout(payload);
                    nativeSaleResult = data;
                    if (data.ok) {
                        localSaleOk = true;
                        serverSale = data.sale;
                        if (data.receipt) receiptData.receipt_text = data.receipt.text;
                        queueBackgroundSync();
                        this.refreshShiftSalesTotal();
                    } else {
                        this.showToast(data.error || 'Local sale failed', 'error');
                        return;
                    }
                } catch (e) {
                    console.warn('[pos] native sale failed:', e);
                    this.showToast(e.message || 'Could not complete sale locally. Check storage and try again.', 'error');
                    return;
                } finally {
                    this.saleProcessing = false;
                }
            } else if (db) {
                await db.transactions.add(txData);
                await db.syncQueue.add({ type: 'transaction_push', ref: localId });
                await db.receipts.add(receiptData);
                this.pendingSyncCount++;
            }

            if (!localSaleOk && !nativeEngine && this.browserOnline) {
                try {
                    const res = await fetch('/api/pos/sales', { method: 'POST', headers: this.csrfHeader(), body: JSON.stringify({
                        branch_id: txData.branch_id, shift_id: txData.shift_id, cashier_id: txData.cashier_id, member_id: txData.member_id,
                        payment_method: txData.payment_method, payment_ref: txData.payment_ref, amount_tendered: txData.amount_tendered,
                        items: txData.items,
                        subtotal: txData.subtotal,
                        discount_total: txData.discount_total,
                        order_discount: txData.order_discount,
                        total: txData.total,
                    }) });
                    const data = await res.json();
                    if (data.success) {
                        serverSale = data.sale;
                        if (db) {
                            await db.transactions.markSynced(localId, data.sale.id);
                            this.pendingSyncCount = Math.max(0, this.pendingSyncCount - 1);
                        }
                    } else {
                        queueBackgroundSync();
                    }
                } catch {
                    queueBackgroundSync();
                }
            }

            if (!this.silentSync) {
                if (localSaleOk && !serverSale) {
                    this.syncStatus = this.browserOnline ? 'partial' : 'offline';
                } else if (!serverSale && !localSaleOk) {
                    this.syncStatus = 'partial';
                }
            }
            this.lastSale = serverSale || { local_id: localId, sale_number: null, total: txData.total, amount_tendered: txData.amount_tendered, change_due: txData.change_due, payment_method: txData.payment_method, offline: !serverSale || localSaleOk, _cart: txData.items };
            this.showReceipt = true;
            this.pushCustomerDisplay('thank_you', {
                total: txData.total,
                change: txData.change_due,
                payment_method: txData.payment_method,
                message: 'Thank you for your purchase!',
            });
            if (this.canUsePrinter()) {
                if (nativeEngine && localSaleOk) {
                    await this.printReceiptAfterSale(nativeSaleResult, txData, receiptData);
                } else {
                    await this.buddyPrintReceipt();
                }
                if (typeof INSABuddy !== 'undefined' && SyncEngine) SyncEngine.pushToBuddy(txData, receiptData);
            }
        },

        closeReceipt() {
            this.showReceipt = false; this.lastSale = null; this.cart = []; this.amountTendered = 0; this.changeAmount = 0;
            this.orderDiscountApplied = 0; this.orderDiscountValue = 0; this.selectedCustomer = null; this.customerSearch = '';
            this.retailScanResult = null; this.setScanFieldValue('retail', '');
            this.screen = 'pos'; this.loadProducts();
            this.pushCustomerDisplay('welcome');
        },

        async openShift() {
            const amount = parseFloat(this.shiftCashInput);
            if (isNaN(amount) || amount < 0) { this.showToast('Invalid amount.', 'error'); return; }
            const nativeEngine = this.useNativeEngine();
            if (nativeEngine) {
                try {
                    const t0 = performance.now();
                    const raw = window.INSAPOS.openLocalShift(JSON.stringify({
                        opening_cash: amount,
                        branch_id: this.config.branchId,
                        cashier_id: this.config.cashierId,
                    }));
                    const data = typeof raw === 'string' ? JSON.parse(raw) : raw;
                    console.log('[pos] openLocalShift', Math.round(performance.now() - t0) + 'ms', data);
                    if (data.ok && data.shift) {
                        this.activeShift = this.mapLocalShift(data.shift);
                        this.applyShiftTotals(data.shift);
                        this.shiftSalesTotal = 0;
                        this.shiftTransactionCount = 0;
                        this.showShiftOpenModal = false;
                        this.shiftCashInput = 0;
                        this.showToast('Shift opened!', 'success');
                        return;
                    }
                    this.showToast(data.error || 'Failed to open shift locally.', 'error');
                } catch (e) {
                    console.warn('[pos] native shift open failed:', e);
                    this.showToast('Failed to open shift locally.', 'error');
                }
                return;
            }
            try {
                const res = await fetch('/api/pos/shift/open', { method: 'POST', headers: this.csrfHeader(), body: JSON.stringify({ opening_cash: amount }) });
                const data = await res.json();
                if (data.success) { this.activeShift = data.shift; this.showShiftOpenModal = false; this.shiftCashInput = 0; this.showToast('Shift opened!', 'success'); }
                else this.showToast(data.message || 'Failed to open shift.', 'error');
            } catch { this.showToast('Network error opening shift.', 'error'); }
        },

        async doCloseShift() {
            const amount = parseFloat(this.shiftCashInput);
            if (isNaN(amount) || amount < 0) { this.showToast('Invalid amount.', 'error'); return; }
            if (window.INSAPOS && typeof window.INSAPOS.closeLocalShift === 'function') {
                try {
                    const raw = window.INSAPOS.closeLocalShift(JSON.stringify({ closing_cash: amount }));
                    const data = typeof raw === 'string' ? JSON.parse(raw) : raw;
                    if (data.ok) {
                        const closed = data.shift || { closing_cash: amount };
                        this.shiftResultData = {
                            ...closed,
                            opening_cash: closed.opening_cash ?? this.activeShift?.opening_cash ?? 0,
                            system_sales_total: closed.system_sales_total ?? closed.total_sales ?? this.shiftSalesTotal ?? 0,
                        };
                        this.showShiftCloseModal = false;
                        this.shiftCashInput = 0;
                        this.showShiftResult = true;
                        this.activeShift = null;
                        this.cart = [];
                        this.orderDiscountApplied = 0;
                        return;
                    }
                } catch (e) { console.warn('[pos] native shift close failed:', e); }
            }
            try {
                const res = await fetch('/api/pos/shift/close', { method: 'POST', headers: this.csrfHeader(), body: JSON.stringify({ closing_cash: amount }) });
                const data = await res.json();
                if (data.success) { this.shiftResultData = data.shift; this.showShiftCloseModal = false; this.shiftCashInput = 0; this.showShiftResult = true; this.activeShift = null; this.cart = []; this.orderDiscountApplied = 0; }
                else this.showToast(data.message || 'Failed to close shift.', 'error');
            } catch { this.showToast('Network error closing shift.', 'error'); }
        },

        async generateXReading() {
            this.showToast('Generating X-Reading...', 'info');
            if (this.useNativeEngine() && typeof window.INSAPOS.getLocalXReading === 'function') {
                try {
                    const raw = window.INSAPOS.getLocalXReading();
                    const data = typeof raw === 'string' ? JSON.parse(raw) : raw;
                    if (data.ok && data.reading) {
                        this.readingData = { ...data.reading, type: 'x' };
                        this.showReadingModal = true;
                        this.showToast('X-Reading generated (local)', 'success');
                        return;
                    }
                } catch (e) { console.warn('[pos] local X-Reading failed:', e); }
            }
            try { const res = await fetch('/api/pos/x-reading', { method: 'POST', headers: this.csrfHeader() }); const data = await res.json();
                if (data.success) { this.readingData = { ...data.reading, type: 'x' }; this.showReadingModal = true; this.showToast('X-Reading generated', 'success'); }
                else this.showToast(data.message || 'Failed to generate X-Reading', 'error');
            } catch { this.showToast('Network error generating X-Reading', 'error'); }
        },

        generateZReading() { this.showZConfirm = true; },

        async doGenerateZReading() {
            this.showZConfirm = false; this.showToast('Generating Z-Reading...', 'info');
            try { const res = await fetch('/api/pos/z-reading', { method: 'POST', headers: this.csrfHeader() }); const data = await res.json();
                if (data.success) { this.readingData = { ...data.reading, type: 'z' }; this.showReadingModal = true; this.showToast('Z-Reading #' + data.reading.z_count + ' generated', 'success'); }
                else this.showToast(data.message || 'Failed to generate Z-Reading', 'error');
            } catch { this.showToast('Network error generating Z-Reading', 'error'); }
        },

        async printReading() {
            if (!this.readingData || !this.buddyConnected) return;
            const r = this.readingData; const lines = []; const div = '================================';
            lines.push('\x1B\x61\x01'); lines.push(r.type === 'z' ? 'Z - R E A D I N G' : 'X - R E A D I N G');
            lines.push(r.type === 'z' ? 'Z-Count: #' + r.z_count : 'Cashier Snapshot'); lines.push(div); lines.push('\x1B\x61\x00');
            lines.push('Date: ' + r.generated_at); lines.push(div);
            if (this.canViewShiftTotals()) {
                lines.push('Total Sales:     ' + parseFloat(r.total_sales).toFixed(2).padStart(14));
            }
            lines.push('Transactions:    ' + String(r.transaction_count).padStart(14));
            lines.push('Discounts:       ' + parseFloat(r.discount_total).toFixed(2).padStart(14));
            lines.push('Voids:           ' + parseFloat(r.void_total).toFixed(2).padStart(14));
            lines.push(div); lines.push('PAYMENT BREAKDOWN');
            const pb = r.payment_breakdown || {};
            for (const [m, a] of Object.entries(pb)) { if (parseFloat(a) > 0) { const l = m.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase()); lines.push(l.padEnd(18) + parseFloat(a).toFixed(2).padStart(14)); } }
            lines.push(div); if (r.type === 'z') lines.push('*** TOTALS RESET ***'); lines.push(''); lines.push('');
            try { await INSABuddy.printText(lines.join('\n')); this.showToast('Reading printed', 'success'); } catch { this.showToast('Print failed', 'error'); }
        },

        stockBadgeClass(product) {
            if (!product || product.stock <= 0) return 'bg-red-100 text-red-700';
            if (product.low_stock || product.stock <= 10) return 'bg-yellow-100 text-yellow-700';
            return 'bg-green-100 text-green-700';
        },

        stockBadgeText(product) {
            if (!product || product.stock <= 0) return 'Out';
            let label = product.stock + ' in stock';
            if (product.near_expiry) label += ' · Exp soon';
            else if (product.low_stock) label += ' · Low';
            return label;
        },
    };
}
</script>
<script src="{{ asset('js/alpine.min.js') }}"></script>
<script>
(function () {
    function revealCashierUi() {
        document.body.classList.remove('insapos-alpine-pending');
        document.querySelectorAll('[x-cloak]').forEach(function (el) { el.removeAttribute('x-cloak'); });
    }
    document.addEventListener('alpine:initialized', revealCashierUi);
    if (typeof window.Alpine !== 'undefined' && window.Alpine.version) {
        revealCashierUi();
    }
    setTimeout(function () {
        if (document.body.classList.contains('insapos-alpine-pending')) {
            console.warn('[INSAPOS] Alpine slow or blocked — revealing cashier shell');
            revealCashierUi();
        }
    }, 12000);
})();
</script>

</body>
</html>
