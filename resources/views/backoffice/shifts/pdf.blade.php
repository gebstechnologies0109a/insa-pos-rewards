<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Shift #{{ $shift->id }} Report</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; margin: 30px; }
        h1 { font-size: 20px; margin-bottom: 5px; }
        .subtitle { color: #888; font-size: 11px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 6px 10px; border: 1px solid #ddd; text-align: left; font-size: 11px; }
        th { background: #f5f5f5; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .section-title { font-size: 14px; font-weight: bold; margin: 20px 0 10px; border-bottom: 2px solid #333; padding-bottom: 5px; }
        .variance-ok { color: #16a34a; }
        .variance-over { color: #2563eb; }
        .variance-short { color: #dc2626; }
        .meta-grid td { border: none; padding: 3px 10px; }
        .meta-grid td:first-child { color: #888; width: 140px; }
        .footer { margin-top: 30px; border-top: 1px solid #ddd; padding-top: 10px; font-size: 10px; color: #999; }
    </style>
</head>
<body>
    <h1>INSA POS — Shift Report</h1>
    <div class="subtitle">Generated {{ now()->format('M d, Y h:i A') }}</div>

    <div class="section-title">Shift Details</div>
    <table class="meta-grid">
        <tr><td>Shift ID</td><td class="font-bold">#{{ $shift->id }}</td></tr>
        <tr><td>Cashier</td><td>{{ $shift->user?->name ?? '—' }}</td></tr>
        <tr><td>Branch</td><td>{{ $shift->branch?->name ?? '—' }}</td></tr>
        <tr><td>Status</td><td class="font-bold">{{ ucfirst($shift->status) }}</td></tr>
        <tr><td>Opened At</td><td>{{ $shift->opened_at?->format('M d, Y h:i:s A') }}</td></tr>
        <tr><td>Closed At</td><td>{{ $shift->closed_at?->format('M d, Y h:i:s A') ?? '—' }}</td></tr>
    </table>

    <div class="section-title">Cash Summary</div>
    @php $expected = $shift->opening_cash + ($shift->system_sales_total ?? 0); $v = $shift->cash_variance; @endphp
    <table class="meta-grid">
        <tr><td>Opening Cash</td><td class="font-bold">P{{ number_format($shift->opening_cash, 2) }}</td></tr>
        <tr><td>System Sales</td><td class="font-bold">{{ $shift->system_sales_total !== null ? 'P' . number_format($shift->system_sales_total, 2) : '—' }}</td></tr>
        <tr><td>Expected Cash</td><td class="font-bold">{{ $shift->status === 'closed' ? 'P' . number_format($expected, 2) : '—' }}</td></tr>
        <tr><td>Closing Cash</td><td class="font-bold">{{ $shift->closing_cash !== null ? 'P' . number_format($shift->closing_cash, 2) : '—' }}</td></tr>
        <tr>
            <td>Variance</td>
            <td class="font-bold {{ $v === null ? '' : ($v == 0 ? 'variance-ok' : ($v > 0 ? 'variance-over' : 'variance-short')) }}">
                @if($v !== null) {{ $v >= 0 ? '+' : '' }}P{{ number_format($v, 2) }} @else — @endif
            </td>
        </tr>
    </table>

    @if($shift->sales->isNotEmpty())
    <div class="section-title">Sales ({{ $shift->sales->count() }})</div>
    <table>
        <thead>
            <tr>
                <th>Sale #</th>
                <th class="text-right">Subtotal</th>
                <th class="text-right">Discount</th>
                <th class="text-right">Total</th>
                <th class="text-center">Payment</th>
                <th>Time</th>
            </tr>
        </thead>
        <tbody>
            @foreach($shift->sales as $sale)
            <tr>
                <td>{{ $sale->sale_number }}</td>
                <td class="text-right">P{{ number_format($sale->subtotal, 2) }}</td>
                <td class="text-right">P{{ number_format($sale->discount_total, 2) }}</td>
                <td class="text-right font-bold">P{{ number_format($sale->total, 2) }}</td>
                <td class="text-center">{{ ucfirst($sale->payment_method) }}</td>
                <td>{{ $sale->sold_at?->format('h:i A') }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th>TOTAL</th>
                <th class="text-right">P{{ number_format($shift->sales->sum('subtotal'), 2) }}</th>
                <th class="text-right">P{{ number_format($shift->sales->sum('discount_total'), 2) }}</th>
                <th class="text-right">P{{ number_format($shift->sales->sum('total'), 2) }}</th>
                <th colspan="2"></th>
            </tr>
        </tfoot>
    </table>
    @endif

    <div class="footer">
        INSA POS System &mdash; Shift Report #{{ $shift->id }} &mdash; Printed {{ now()->format('M d, Y h:i A') }}
    </div>
</body>
</html>
