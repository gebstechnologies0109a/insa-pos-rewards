<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>INSA POS — Device Logs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .log-row:hover { background: #f9fafb; }
        pre { white-space: pre-wrap; word-break: break-all; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-white shadow px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <h1 class="text-xl font-bold text-gray-800">INSAPOS Device Logs</h1>
            <span class="text-sm text-gray-500">{{ $logs->total() }} entries</span>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ url('/insaposlogs') }}" class="text-sm text-blue-600 hover:underline">Refresh</a>
            <button onclick="clearLogs()" class="px-4 py-2 bg-red-600 text-white rounded text-sm hover:bg-red-700">Clear All</button>
            <a href="{{ route('backoffice.dashboard') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300">Back Office</a>
        </div>
    </header>

    <div class="p-6">
        <!-- Filters -->
        <form class="flex gap-3 mb-4" method="GET">
            <select name="level" class="border rounded px-3 py-2 text-sm bg-white">
                <option value="">All Levels</option>
                @foreach(['debug','info','warn','error'] as $lvl)
                    <option value="{{ $lvl }}" {{ request('level') === $lvl ? 'selected' : '' }}>{{ ucfirst($lvl) }}</option>
                @endforeach
            </select>
            <select name="device_id" class="border rounded px-3 py-2 text-sm bg-white">
                <option value="">All Devices</option>
                @foreach($devices as $did)
                    <option value="{{ $did }}" {{ request('device_id') === $did ? 'selected' : '' }}>{{ $did }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">Filter</button>
        </form>

        <!-- Auto-refresh toggle -->
        <div class="flex items-center gap-2 mb-4">
            <label class="text-sm text-gray-600">
                <input type="checkbox" id="autoRefresh" class="mr-1" onchange="toggleAutoRefresh()"> Auto-refresh (10s)
            </label>
            <span id="refreshCountdown" class="text-xs text-gray-400"></span>
        </div>

        <!-- Log Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left p-3 font-medium w-40">Time</th>
                        <th class="text-left p-3 font-medium w-20">Level</th>
                        <th class="text-left p-3 font-medium w-24">Tag</th>
                        <th class="text-left p-3 font-medium">Message</th>
                        <th class="text-left p-3 font-medium w-48">Device</th>
                        <th class="text-left p-3 font-medium w-32">URL</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr class="log-row border-b border-gray-100">
                        <td class="p-3 text-gray-500 font-mono text-xs">{{ $log->created_at->format('M d H:i:s') }}</td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded text-xs font-bold
                                @if($log->level === 'error') bg-red-100 text-red-700
                                @elseif($log->level === 'warn') bg-yellow-100 text-yellow-700
                                @elseif($log->level === 'debug') bg-gray-100 text-gray-600
                                @else bg-blue-100 text-blue-700
                                @endif">{{ strtoupper($log->level) }}</span>
                        </td>
                        <td class="p-3 font-mono text-xs text-gray-500">{{ $log->tag }}</td>
                        <td class="p-3">
                            <pre class="text-xs">{{ $log->message }}</pre>
                            @if($log->extra)
                                <pre class="text-xs text-gray-400 mt-1">{{ $log->extra }}</pre>
                            @endif
                        </td>
                        <td class="p-3 text-xs text-gray-500">
                            <div>{{ $log->device_model }}</div>
                            <div class="text-gray-400">{{ $log->device_id }}</div>
                            <div class="text-gray-400">Android {{ $log->android_version }} | v{{ $log->app_version }}</div>
                        </td>
                        <td class="p-3 text-xs text-gray-400 truncate max-w-[200px]" title="{{ $log->url }}">{{ $log->url }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="p-12 text-center text-gray-400">
                            No device logs yet. Logs will appear here when the INSAPOS app sends them.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $logs->withQueryString()->links() }}</div>
    </div>

    <script>
        let refreshTimer = null;
        function toggleAutoRefresh() {
            if (document.getElementById('autoRefresh').checked) {
                let count = 10;
                document.getElementById('refreshCountdown').textContent = count + 's';
                refreshTimer = setInterval(() => {
                    count--;
                    document.getElementById('refreshCountdown').textContent = count + 's';
                    if (count <= 0) location.reload();
                }, 1000);
            } else {
                clearInterval(refreshTimer);
                document.getElementById('refreshCountdown').textContent = '';
            }
        }
        function clearLogs() {
            if (!confirm('Clear all device logs?')) return;
            fetch('/api/pos/device-log/clear', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                }
            }).then(() => location.reload());
        }
    </script>
</body>
</html>
