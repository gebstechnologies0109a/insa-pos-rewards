@extends('layouts.backoffice')
@section('page-title', 'Sales Analytics')

@section('content')
<div x-data="analyticsApp()" x-init="load()">

<!-- RANGE SELECTOR -->
<div class="bg-white rounded-lg shadow p-4 mb-6 flex flex-wrap items-center gap-3">
    <span class="text-sm font-semibold text-gray-700">Range:</span>
    <template x-for="r in ranges" :key="r.value">
        <button @click="range = r.value; load()" class="px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors"
                :class="range === r.value ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400'"
                x-text="r.label"></button>
    </template>
    <div class="flex items-center gap-2 ml-2">
        <input type="date" x-model="customFrom" class="px-2 py-1.5 border rounded text-xs">
        <span class="text-gray-400 text-xs">to</span>
        <input type="date" x-model="customTo" class="px-2 py-1.5 border rounded text-xs">
        <button @click="range = 'custom'; load()" class="px-3 py-1.5 bg-gray-700 text-white rounded text-xs font-medium hover:bg-gray-800">Apply</button>
    </div>
    <div class="ml-auto flex items-center gap-2">
        <span class="text-sm font-semibold text-gray-700">Top:</span>
        <template x-for="n in [10, 20, 50, 100]" :key="n">
            <button @click="topLimit = n; load()" class="px-2 py-1 rounded text-xs font-medium border"
                    :class="topLimit === n ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400'"
                    x-text="n"></button>
        </template>
    </div>
    <div x-show="loading" class="ml-2">
        <svg class="animate-spin h-5 w-5 text-blue-600" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
    </div>
</div>

<!-- ERROR BANNER -->
<div x-show="errorMsg" class="bg-red-100 border border-red-300 text-red-800 rounded-lg p-4 mb-6 text-sm" x-text="errorMsg"></div>

<!-- REAL-TIME STRIP -->
<div class="grid grid-cols-5 gap-4 mb-6">
    <div class="bg-gradient-to-br from-green-500 to-green-700 rounded-lg shadow p-4 text-white">
        <div class="text-xs uppercase opacity-80 font-medium">Today's Revenue</div>
        <div class="text-2xl font-bold mt-1" x-text="'₱' + fmt(rt.today_revenue)"></div>
        <div class="text-xs opacity-80 mt-1" x-text="rt.today_tx + ' transactions'"></div>
    </div>
    <div class="bg-gradient-to-br from-blue-500 to-blue-700 rounded-lg shadow p-4 text-white">
        <div class="text-xs uppercase opacity-80 font-medium">Running Shift Sales</div>
        <div class="text-2xl font-bold mt-1" x-text="'₱' + fmt(rt.running_sales)"></div>
        <div class="text-xs opacity-80 mt-1" x-text="rt.open_shifts + ' open shift(s)'"></div>
    </div>
    <div class="bg-gradient-to-br from-purple-500 to-purple-700 rounded-lg shadow p-4 text-white">
        <div class="text-xs uppercase opacity-80 font-medium">Period Revenue</div>
        <div class="text-2xl font-bold mt-1" x-text="'₱' + fmt(s.revenue)"></div>
        <div class="text-xs opacity-80 mt-1">
            <span x-show="s.growth_pct !== null" :class="s.growth_pct >= 0 ? 'text-green-200' : 'text-red-200'" x-text="(s.growth_pct >= 0 ? '+' : '') + s.growth_pct + '% vs prev'"></span>
            <span x-show="s.growth_pct === null" class="text-white/60">No prior data</span>
        </div>
    </div>
    <div class="bg-gradient-to-br from-amber-500 to-amber-700 rounded-lg shadow p-4 text-white">
        <div class="text-xs uppercase opacity-80 font-medium">Avg Ticket</div>
        <div class="text-2xl font-bold mt-1" x-text="'₱' + fmt(s.avg_ticket)"></div>
        <div class="text-xs opacity-80 mt-1" x-text="s.tx_count + ' sales · ' + s.items_sold + ' items'"></div>
    </div>
    <div class="bg-gradient-to-br from-red-500 to-red-700 rounded-lg shadow p-4 text-white">
        <div class="text-xs uppercase opacity-80 font-medium">Low Stock Alerts</div>
        <div class="text-2xl font-bold mt-1" x-text="rt.low_stock_count"></div>
        <div class="text-xs opacity-80 mt-1">items at/below 10 units</div>
    </div>
</div>

<!-- SUMMARY CARDS ROW 2 -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase font-medium">Discounts Given</div>
        <div class="text-xl font-bold text-red-600 mt-1" x-text="'₱' + fmt(s.discounts)"></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase font-medium">Unique Customers</div>
        <div class="text-xl font-bold text-blue-700 mt-1" x-text="s.unique_customers"></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-xs text-gray-500 uppercase font-medium">Net Revenue</div>
        <div class="text-xl font-bold text-green-700 mt-1" x-text="'₱' + fmt(s.revenue - s.discounts)"></div>
    </div>
</div>

<!-- CHARTS ROW -->
<div class="grid grid-cols-2 gap-6 mb-6">
    <!-- Daily Sales Chart -->
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-bold text-gray-700 mb-3">Daily Sales Trend</h3>
        <canvas id="dailyChart" height="200"></canvas>
    </div>
    <!-- Hourly Sales Chart -->
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-bold text-gray-700 mb-3">Hourly Sales Distribution</h3>
        <canvas id="hourlyChart" height="200"></canvas>
    </div>
</div>

<div class="grid grid-cols-2 gap-6 mb-6">
    <!-- Payment Breakdown -->
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-bold text-gray-700 mb-3">Payment Methods</h3>
        <canvas id="paymentChart" height="200"></canvas>
    </div>
    <!-- Inventory Alerts -->
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="text-sm font-bold text-gray-700 mb-3">Low Stock Inventory</h3>
        <div class="max-h-[220px] overflow-y-auto">
            <table class="w-full text-xs">
                <thead class="sticky top-0 bg-white"><tr><th class="text-left p-1.5 font-medium">Product</th><th class="text-left p-1.5">SKU</th><th class="text-left p-1.5">Category</th><th class="text-right p-1.5">Stock</th></tr></thead>
                <tbody>
                    <template x-for="item in inv" :key="item.id">
                        <tr class="border-t hover:bg-gray-50">
                            <td class="p-1.5 font-medium" x-text="item.name"></td>
                            <td class="p-1.5 text-gray-500" x-text="item.sku"></td>
                            <td class="p-1.5 text-gray-500" x-text="item.category || '-'"></td>
                            <td class="p-1.5 text-right font-bold" :class="item.stock <= 5 ? 'text-red-600' : 'text-yellow-600'" x-text="item.stock"></td>
                        </tr>
                    </template>
                    <tr x-show="inv.length === 0"><td colspan="4" class="p-4 text-center text-gray-400">No low-stock items</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TOP SELLING ITEMS -->
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <h3 class="text-sm font-bold text-gray-700 mb-3">Top <span x-text="topLimit"></span> Best Selling Items</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-gray-50"><tr>
                <th class="text-left p-2 font-medium">#</th>
                <th class="text-left p-2 font-medium">Product</th>
                <th class="text-left p-2 font-medium">SKU</th>
                <th class="text-right p-2 font-medium">Qty Sold</th>
                <th class="text-right p-2 font-medium">Revenue</th>
                <th class="text-right p-2 font-medium">Discount</th>
                <th class="text-right p-2 font-medium">Sales Count</th>
                <th class="text-center p-2 font-medium">Action</th>
            </tr></thead>
            <tbody>
                <template x-for="(item, idx) in topItems" :key="item.product_id">
                    <tr class="border-t hover:bg-gray-50">
                        <td class="p-2 text-gray-400" x-text="idx + 1"></td>
                        <td class="p-2 font-medium" x-text="item.product_name"></td>
                        <td class="p-2 text-gray-500" x-text="item.sku || '-'"></td>
                        <td class="p-2 text-right font-bold" x-text="item.total_qty"></td>
                        <td class="p-2 text-right font-mono text-green-700" x-text="'₱' + fmt(item.total_revenue)"></td>
                        <td class="p-2 text-right font-mono text-red-500" x-text="'₱' + fmt(item.total_discount)"></td>
                        <td class="p-2 text-right" x-text="item.sale_count"></td>
                        <td class="p-2 text-center">
                            <button @click="viewProduct(item.product_id)" class="text-blue-600 hover:underline text-xs font-medium">View</button>
                        </td>
                    </tr>
                </template>
                <tr x-show="topItems.length === 0"><td colspan="8" class="p-4 text-center text-gray-400">No sales data for this period</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- TOP SELLING CATEGORIES -->
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <h3 class="text-sm font-bold text-gray-700 mb-3">Top <span x-text="topLimit"></span> Best Selling Categories</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-gray-50"><tr>
                <th class="text-left p-2 font-medium">#</th>
                <th class="text-left p-2 font-medium">Category</th>
                <th class="text-right p-2 font-medium">Products</th>
                <th class="text-right p-2 font-medium">Qty Sold</th>
                <th class="text-right p-2 font-medium">Revenue</th>
                <th class="text-right p-2 font-medium">Sales Count</th>
            </tr></thead>
            <tbody>
                <template x-for="(cat, idx) in topCats" :key="cat.category_id">
                    <tr class="border-t hover:bg-gray-50">
                        <td class="p-2 text-gray-400" x-text="idx + 1"></td>
                        <td class="p-2 font-medium" x-text="cat.category_name"></td>
                        <td class="p-2 text-right" x-text="cat.product_count"></td>
                        <td class="p-2 text-right font-bold" x-text="cat.total_qty"></td>
                        <td class="p-2 text-right font-mono text-green-700" x-text="'₱' + fmt(cat.total_revenue)"></td>
                        <td class="p-2 text-right" x-text="cat.sale_count"></td>
                    </tr>
                </template>
                <tr x-show="topCats.length === 0"><td colspan="6" class="p-4 text-center text-gray-400">No sales data for this period</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- PRODUCT DETAIL MODAL -->
<div x-show="showProductModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" x-transition @click.self="showProductModal = false">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-800">
                Product Analytics: <span class="text-blue-700" x-text="pd.product?.name || ''"></span>
            </h2>
            <button @click="showProductModal = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div x-show="pdLoading" class="text-center py-8 text-gray-400">Loading...</div>
        <div x-show="!pdLoading">
            <div class="grid grid-cols-4 gap-4 mb-4">
                <div class="bg-blue-50 rounded-lg p-3 text-center">
                    <div class="text-xs text-gray-500">Total Qty Sold</div>
                    <div class="text-xl font-bold text-blue-700" x-text="pd.totals?.total_qty || 0"></div>
                </div>
                <div class="bg-green-50 rounded-lg p-3 text-center">
                    <div class="text-xs text-gray-500">Total Revenue</div>
                    <div class="text-xl font-bold text-green-700" x-text="'₱' + fmt(pd.totals?.total_revenue || 0)"></div>
                </div>
                <div class="bg-red-50 rounded-lg p-3 text-center">
                    <div class="text-xs text-gray-500">Total Discount</div>
                    <div class="text-xl font-bold text-red-600" x-text="'₱' + fmt(pd.totals?.total_discount || 0)"></div>
                </div>
                <div class="bg-purple-50 rounded-lg p-3 text-center">
                    <div class="text-xs text-gray-500">Appearances</div>
                    <div class="text-xl font-bold text-purple-700" x-text="pd.totals?.sale_count || 0"></div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><h4 class="text-xs font-bold text-gray-600 mb-2">Daily Sales</h4><canvas id="pdDailyChart" height="180"></canvas></div>
                <div><h4 class="text-xs font-bold text-gray-600 mb-2">Hourly Pattern</h4><canvas id="pdHourlyChart" height="180"></canvas></div>
            </div>
        </div>
    </div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function analyticsApp() {
    return {
        range: '7d',
        customFrom: '',
        customTo: '',
        topLimit: 10,
        loading: false,
        ranges: [
            { value: '1d', label: 'Today' },
            { value: '7d', label: '7 Days' },
            { value: '14d', label: '14 Days' },
            { value: '30d', label: '30 Days' },
            { value: '1m', label: '1 Month' },
            { value: '2m', label: '2 Months' },
            { value: '3m', label: '3 Months' },
            { value: '6m', label: '6 Months' },
            { value: '12m', label: '12 Months' },
        ],
        s: { revenue: 0, discounts: 0, avg_ticket: 0, tx_count: 0, items_sold: 0, unique_customers: 0, growth_pct: null },
        rt: { today_tx: 0, today_revenue: 0, open_shifts: 0, running_sales: 0, low_stock_count: 0 },
        topItems: [],
        topCats: [],
        inv: [],
        daily: [],
        hourly: [],
        payment: [],

        showProductModal: false,
        pdLoading: false,
        pd: { product: null, totals: null, daily: [], hourly: [] },
        errorMsg: '',

        _dailyChart: null,
        _hourlyChart: null,
        _paymentChart: null,
        _pdDailyChart: null,
        _pdHourlyChart: null,

        fmt(v) { return parseFloat(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },

        async load() {
            this.loading = true;
            this.errorMsg = '';
            const params = new URLSearchParams({ range: this.range, top: this.topLimit });
            if (this.range === 'custom') { params.set('from', this.customFrom); params.set('to', this.customTo); }
            try {
                const res = await fetch('/backoffice/analytics/data?' + params, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                    credentials: 'same-origin',
                });
                if (!res.ok) {
                    let msg = 'Server returned ' + res.status;
                    try {
                        const errJson = await res.json();
                        msg += ': ' + (errJson.error || JSON.stringify(errJson).substring(0, 200));
                    } catch { msg += ' (could not parse response)'; }
                    this.errorMsg = msg;
                    this.loading = false;
                    return;
                }
                const d = await res.json();
                this.s = d.summary;
                this.rt = d.realtime;
                this.topItems = d.topItems;
                this.topCats = d.topCats;
                this.daily = d.daily;
                this.hourly = d.hourly;
                this.payment = d.payment;
                this.inv = d.inventory;
                this.$nextTick(() => { this.renderCharts(); });
            } catch (e) {
                console.error('Analytics load error', e);
                this.errorMsg = 'Failed to load analytics: ' + e.message;
            }
            this.loading = false;
        },

        renderCharts() {
            this.renderDaily();
            this.renderHourly();
            this.renderPayment();
        },

        renderDaily() {
            if (this._dailyChart) this._dailyChart.destroy();
            const el = document.getElementById('dailyChart');
            if (!el) return;
            this._dailyChart = new Chart(el.getContext('2d'), {
                type: 'line',
                data: {
                    labels: this.daily.map(d => d.date),
                    datasets: [{
                        label: 'Revenue',
                        data: this.daily.map(d => parseFloat(d.revenue)),
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,0.1)',
                        fill: true,
                        tension: 0.3,
                    }, {
                        label: 'Transactions',
                        data: this.daily.map(d => d.tx_count),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,0.1)',
                        fill: false,
                        tension: 0.3,
                        yAxisID: 'y1',
                    }],
                },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: v => '₱' + v.toLocaleString() } },
                        y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } },
                    },
                },
            });
        },

        renderHourly() {
            if (this._hourlyChart) this._hourlyChart.destroy();
            const el = document.getElementById('hourlyChart');
            if (!el) return;
            const hours = Array.from({ length: 24 }, (_, i) => i);
            const map = {};
            this.hourly.forEach(h => { map[h.hour] = h; });
            this._hourlyChart = new Chart(el.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: hours.map(h => (h < 10 ? '0' : '') + h + ':00'),
                    datasets: [{
                        label: 'Revenue',
                        data: hours.map(h => parseFloat(map[h]?.revenue || 0)),
                        backgroundColor: 'rgba(37,99,235,0.7)',
                    }],
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { callback: v => '₱' + v.toLocaleString() } } },
                },
            });
        },

        renderPayment() {
            if (this._paymentChart) this._paymentChart.destroy();
            const el = document.getElementById('paymentChart');
            if (!el) return;
            const colors = ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
            this._paymentChart = new Chart(el.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: this.payment.map(p => (p.payment_method || 'cash').replace('_', ' ')),
                    datasets: [{
                        data: this.payment.map(p => parseFloat(p.revenue)),
                        backgroundColor: colors.slice(0, this.payment.length),
                    }],
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } },
                        tooltip: { callbacks: { label: ctx => '₱' + parseFloat(ctx.raw).toLocaleString('en-PH', { minimumFractionDigits: 2 }) } },
                    },
                },
            });
        },

        async viewProduct(productId) {
            this.showProductModal = true;
            this.pdLoading = true;
            this.pd = { product: null, totals: null, daily: [], hourly: [] };
            const params = new URLSearchParams({ range: this.range });
            if (this.range === 'custom') { params.set('from', this.customFrom); params.set('to', this.customTo); }
            try {
                const res = await fetch('/backoffice/analytics/product/' + productId + '?' + params, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }
                });
                const d = await res.json();
                this.pd = d;
            } catch (e) { console.error('Product detail error', e); }
            this.pdLoading = false;
            this.$nextTick(() => { this.renderProductCharts(); });
        },

        renderProductCharts() {
            if (this._pdDailyChart) this._pdDailyChart.destroy();
            if (this._pdHourlyChart) this._pdHourlyChart.destroy();
            const dEl = document.getElementById('pdDailyChart');
            const hEl = document.getElementById('pdHourlyChart');
            if (dEl && this.pd.daily?.length) {
                this._pdDailyChart = new Chart(dEl.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: this.pd.daily.map(d => d.date),
                        datasets: [{
                            label: 'Qty Sold',
                            data: this.pd.daily.map(d => d.qty),
                            backgroundColor: 'rgba(37,99,235,0.7)',
                        }, {
                            label: 'Revenue',
                            data: this.pd.daily.map(d => parseFloat(d.revenue)),
                            backgroundColor: 'rgba(16,185,129,0.7)',
                            yAxisID: 'y1',
                        }],
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } },
                        scales: { y: { beginAtZero: true }, y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { callback: v => '₱' + v } } },
                    },
                });
            }
            if (hEl && this.pd.hourly?.length) {
                const hours = Array.from({ length: 24 }, (_, i) => i);
                const map = {};
                this.pd.hourly.forEach(h => { map[h.hour] = h; });
                this._pdHourlyChart = new Chart(hEl.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: hours.map(h => (h < 10 ? '0' : '') + h + ':00'),
                        datasets: [{ label: 'Qty', data: hours.map(h => parseInt(map[h]?.qty || 0)), backgroundColor: 'rgba(139,92,246,0.7)' }],
                    },
                    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
                });
            }
        },
    };
}
</script>
@endsection
