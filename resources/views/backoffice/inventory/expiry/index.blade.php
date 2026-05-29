@extends('layouts.backoffice')

@section('page-title', 'Expiry Dashboard')

@section('content')
<h1 class="text-2xl font-bold mb-6">Expiry Dashboard</h1>

@if(session('success'))
<div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="mb-4 p-3 bg-red-100 text-red-800 rounded">{{ session('error') }}</div>
@endif
@if(!empty($migrationPending))
<div class="mb-4 p-3 bg-amber-100 text-amber-900 rounded">
    Run <code class="text-sm">php artisan migrate</code> to enable expiry alerts.
</div>
@endif

@php
    $tab = request('tab', $filter === 'slow_moving' ? 'slow' : (request('alert_type', 'active')));
    $baseQuery = request()->except(['tab', 'bucket', 'alert_type']);
@endphp

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <a href="{{ route('backoffice.inventory.expiry', array_merge($baseQuery, ['tab' => 'thirty_day', 'alert_type' => 'thirty_day', 'bucket' => 'active'])) }}"
       class="bg-white rounded-lg shadow p-4 block hover:ring-2 hover:ring-yellow-300 {{ $tab === 'thirty_day' ? 'ring-2 ring-yellow-400' : '' }}">
        <div class="text-sm text-gray-500">30-day warnings</div>
        <div class="text-2xl font-bold text-yellow-600">{{ $counts['thirty_day'] }}</div>
    </a>
    <a href="{{ route('backoffice.inventory.expiry', array_merge($baseQuery, ['tab' => 'seven_day', 'alert_type' => 'seven_day', 'bucket' => 'active'])) }}"
       class="bg-white rounded-lg shadow p-4 block hover:ring-2 hover:ring-orange-300 {{ $tab === 'seven_day' ? 'ring-2 ring-orange-400' : '' }}">
        <div class="text-sm text-gray-500">7-day warnings</div>
        <div class="text-2xl font-bold text-orange-600">{{ $counts['seven_day'] }}</div>
    </a>
    <a href="{{ route('backoffice.inventory.expiry', array_merge($baseQuery, ['tab' => 'expired', 'alert_type' => 'expired', 'bucket' => 'active'])) }}"
       class="bg-white rounded-lg shadow p-4 block hover:ring-2 hover:ring-red-300 {{ $tab === 'expired' ? 'ring-2 ring-red-400' : '' }}">
        <div class="text-sm text-gray-500">Expired</div>
        <div class="text-2xl font-bold text-red-600">{{ $counts['expired'] }}</div>
    </a>
    <a href="{{ route('backoffice.inventory.expiry', array_merge($baseQuery, ['tab' => 'slow', 'bucket' => 'slow_moving'])) }}"
       class="bg-white rounded-lg shadow p-4 block hover:ring-2 hover:ring-blue-300 {{ $tab === 'slow' ? 'ring-2 ring-blue-400' : '' }}">
        <div class="text-sm text-gray-500">Slow moving (60d)</div>
        <div class="text-2xl font-bold text-blue-600">{{ $counts['slow_moving'] }}</div>
    </a>
</div>

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <x-branch-selector :branch-id="$branchId" :branches="$branches" :auto-submit="false" />
        <input type="hidden" name="tab" value="{{ $tab }}">
        @if($tab !== 'slow')
        <input type="hidden" name="bucket" value="active">
        <input type="hidden" name="alert_type" value="{{ in_array($tab, ['thirty_day','seven_day','expired']) ? $tab : '' }}">
        @else
        <input type="hidden" name="bucket" value="slow_moving">
        @endif
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded">Apply branch</button>
        <a href="{{ route('backoffice.inventory.expiry', ['branch_id' => $branchId]) }}" class="text-sm text-gray-600 hover:underline">Reset filters</a>
    </form>
</div>

@if($filter === 'slow_moving')
@include('backoffice.inventory.partials.expiry-slow-table', ['slowMoving' => $slowMoving])
@else
@include('backoffice.inventory.partials.expiry-alerts-table', ['alerts' => $alerts])
@endif
@endsection
