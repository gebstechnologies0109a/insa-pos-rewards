@extends('layouts.epayplus')
@section('title', 'Device Logs: ' . $device->name)

@section('content')
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="{{ route('epayplus.devices') }}">Devices</a></li>
            <li class="breadcrumb-item"><a href="{{ route('epayplus.devices.show', $device) }}">{{ $device->name }}</a></li>
            <li class="breadcrumb-item active">Logs</li>
        </ol>
    </nav>
    <h4>Device Logs — {{ $device->name }}</h4>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <form method="GET" class="row g-2">
            <div class="col-md-3">
                <select name="level" class="form-select form-select-sm">
                    <option value="">All Levels</option>
                    @foreach(['debug','info','warning','error','critical'] as $lvl)
                    <option value="{{ $lvl }}" {{ request('level') == $lvl ? 'selected' : '' }}>{{ ucfirst($lvl) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr><th>Time</th><th>Level</th><th>Tag</th><th>Message</th></tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td><small>{{ $log->created_at?->format('Y-m-d H:i:s') }}</small></td>
                    <td>
                        @php $lc = ['error'=>'danger','critical'=>'danger','warning'=>'warning','info'=>'info','debug'=>'secondary']; @endphp
                        <span class="badge bg-{{ $lc[$log->level] ?? 'secondary' }}">{{ $log->level }}</span>
                    </td>
                    <td><code>{{ $log->tag ?? '—' }}</code></td>
                    <td>{{ $log->message }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center py-4 text-muted">No logs found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
    <div class="card-footer bg-white">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
