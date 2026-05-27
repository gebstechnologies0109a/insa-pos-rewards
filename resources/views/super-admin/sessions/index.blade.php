@extends('layouts.super-admin')

@section('page-title', 'Active POS Sessions')

@section('content')
<div class="bg-white rounded-xl shadow-sm border">
    <div class="px-6 py-4 border-b flex items-center justify-between">
        <h3 class="font-semibold text-gray-900">Active cashier seats (all branches)</h3>
        <a href="{{ route('super-admin.licenses.index') }}" class="text-sm text-indigo-600 hover:underline">Manage licenses</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-6 py-3 font-medium text-gray-600">Branch</th>
                    <th class="text-left px-6 py-3 font-medium text-gray-600">User</th>
                    <th class="text-left px-6 py-3 font-medium text-gray-600">Device</th>
                    <th class="text-left px-6 py-3 font-medium text-gray-600">Started</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($sessions as $session)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3">{{ $session->branch?->name ?? '—' }}</td>
                    <td class="px-6 py-3">{{ $session->user?->name ?? '—' }}</td>
                    <td class="px-6 py-3 font-mono text-xs text-gray-600">{{ Str::limit($session->device_fingerprint, 24) }}</td>
                    <td class="px-6 py-3">{{ $session->started_at?->diffForHumans() }}</td>
                    <td class="px-6 py-3 text-center">
                        <form method="POST" action="{{ route('super-admin.sessions.end', $session) }}" onsubmit="return confirm('End this session?');">
                            @csrf
                            <button type="submit" class="text-red-600 hover:underline text-xs font-medium">End session</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">No active POS sessions.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
