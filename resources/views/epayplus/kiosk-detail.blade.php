@extends('layouts.epayplus')
@section('title', 'Kiosk: ' . $device->name)

@section('content')
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="{{ route('epayplus.kiosks') }}">Kiosks</a></li>
            <li class="breadcrumb-item active">{{ $device->name }}</li>
        </ol>
    </nav>
    <h4>{{ $device->name ?? 'Kiosk Details' }}</h4>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        {{-- Configuration --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-medium">Kiosk Configuration</div>
            <div class="card-body">
                <form method="POST" action="{{ route('epayplus.kiosks.config', $device) }}">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <label class="form-label">Accepted Denominations</label>
                        <div class="row g-2">
                            @foreach([1, 5, 10, 20, 50, 100, 200, 500, 1000] as $denom)
                            <div class="col-auto">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="accepted_denominations[]"
                                        value="{{ $denom }}" id="denom{{ $denom }}"
                                        {{ in_array($denom, $config['accepted_denominations'] ?? [1,5,10,20]) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="denom{{ $denom }}">₱{{ $denom }}</label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Max Transaction Amount (₱)</label>
                        <input type="number" name="max_transaction_amount" class="form-control"
                            value="{{ $config['max_transaction_amount'] ?? 1000 }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Idle Timeout (seconds)</label>
                        <input type="number" name="idle_timeout_seconds" class="form-control"
                            value="{{ $config['idle_timeout_seconds'] ?? 60 }}">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="auto_print_receipt" value="1"
                            id="autoPrint" {{ ($config['auto_print_receipt'] ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="autoPrint">Auto-print receipt</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="wifi_enabled" value="1"
                            id="wifiEnabled" {{ ($config['wifi_enabled'] ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="wifiEnabled">WiFi Vendo Enabled</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">WiFi Rate (₱/minute)</label>
                        <input type="number" step="0.5" name="wifi_rate_per_minute" class="form-control"
                            value="{{ $config['wifi_rate_per_minute'] ?? 1 }}">
                    </div>
                    <button type="submit" class="btn btn-success">Update Configuration</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        {{-- Record Collection --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-medium">Record Cash Collection</div>
            <div class="card-body">
                <form method="POST" action="{{ route('epayplus.kiosks.collection', $device) }}">
                    @csrf
                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label class="form-label">Total Amount (₱)</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label class="form-label">Coins (₱)</label>
                            <input type="number" step="0.01" name="coins_amount" class="form-control" value="0">
                        </div>
                        <div class="col">
                            <label class="form-label">Bills (₱)</label>
                            <input type="number" step="0.01" name="bills_amount" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Collected By</label>
                        <input type="text" name="collected_by" class="form-control" required value="{{ auth()->user()->name ?? '' }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Record Collection</button>
                </form>
            </div>
        </div>

        {{-- Collection History --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-medium">Collection History</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Date</th><th>Amount</th><th>By</th></tr>
                    </thead>
                    <tbody>
                        @forelse($collections as $col)
                        <tr>
                            <td><small>{{ $col->collected_at?->format('M d, Y H:i') }}</small></td>
                            <td class="fw-medium">₱{{ number_format($col->amount, 2) }}</td>
                            <td>{{ $col->collected_by }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center py-3 text-muted">No collections yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($collections->hasPages())
            <div class="card-footer bg-white">{{ $collections->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
