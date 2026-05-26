<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Shifts Report</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #333; margin: 20px; }
        h1 { font-size: 16px; margin-bottom: 3px; }
        .subtitle { color: #888; font-size: 9px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 5px 6px; border: 1px solid #ddd; font-size: 9px; }
        th { background: #f5f5f5; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .variance-ok { color: #16a34a; }
        .variance-over { color: #2563eb; }
        .variance-short { color: #dc2626; }
    </style>
</head>
<body>
    @php $brandName = str_contains(request()->getHost(), 'epayplus') ? 'ePay Plus' : 'INSA POS'; @endphp
    <h1>{{ $brandName }} — Shifts Report</h1>
    <div class="subtitle">Generated {{ now()->format('M d, Y h:i A') }} &mdash; {{ $shifts->count() }} shifts</div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Cashier</th>
                <th>Branch</th>
                <th>Opened</th>
                <th>Closed</th>
                <th class="text-right">Opening</th>
                <th class="text-right">Closing</th>
                <th class="text-right">Sales</th>
                <th class="text-right">Variance</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($shifts as $s)
            @php $v = $s->cash_variance; @endphp
            <tr>
                <td>{{ $s->id }}</td>
                <td>{{ $s->user?->name ?? '—' }}</td>
                <td>{{ $s->branch?->name ?? '—' }}</td>
                <td>{{ $s->opened_at?->format('m/d/y H:i') }}</td>
                <td>{{ $s->closed_at?->format('m/d/y H:i') ?? '—' }}</td>
                <td class="text-right">P{{ number_format($s->opening_cash, 2) }}</td>
                <td class="text-right">{{ $s->closing_cash !== null ? 'P' . number_format($s->closing_cash, 2) : '—' }}</td>
                <td class="text-right">{{ $s->system_sales_total !== null ? 'P' . number_format($s->system_sales_total, 2) : '—' }}</td>
                <td class="text-right {{ $v === null ? '' : ($v == 0 ? 'variance-ok' : ($v > 0 ? 'variance-over' : 'variance-short')) }}">
                    @if($v !== null) {{ $v >= 0 ? '+' : '' }}P{{ number_format($v, 2) }} @else — @endif
                </td>
                <td class="text-center">{{ ucfirst($s->status) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
