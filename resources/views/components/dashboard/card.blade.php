@props([
    'title',
    'value',
    'tone' => 'default',
    'subtitle' => null,
    'href' => null,
])

@php
    $tones = [
        'default' => ['wrap' => 'bg-white', 'label' => 'text-gray-500', 'value' => 'text-gray-900'],
        'success' => ['wrap' => 'bg-white', 'label' => 'text-green-600', 'value' => 'text-green-600'],
        'warning' => ['wrap' => 'bg-white', 'label' => 'text-yellow-600', 'value' => 'text-yellow-600'],
        'danger'  => ['wrap' => 'bg-white', 'label' => 'text-red-600', 'value' => 'text-red-600'],
        'info'    => ['wrap' => 'bg-white', 'label' => 'text-blue-600', 'value' => 'text-blue-700'],
        'muted'   => ['wrap' => 'bg-gray-50', 'label' => 'text-gray-500', 'value' => 'text-gray-900'],
        'green-panel' => ['wrap' => 'bg-green-50', 'label' => 'text-green-600', 'value' => 'text-green-600'],
        'blue-panel'  => ['wrap' => 'bg-blue-50', 'label' => 'text-blue-600', 'value' => 'text-blue-700'],
        'red-panel'   => ['wrap' => 'bg-red-50', 'label' => 'text-red-600', 'value' => 'text-red-600'],
    ];
    $palette = $tones[$tone] ?? $tones['default'];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg shadow p-5 ' . $palette['wrap']]) }}>
    @if($href)
        <a href="{{ $href }}" class="block group">
    @endif
    <div class="text-sm {{ $palette['label'] }}">{{ $title }}</div>
    <div class="text-3xl font-bold mt-1 {{ $palette['value'] }}">{{ $value }}</div>
    @if($subtitle)
        <div class="text-xs text-gray-400 mt-1">{{ $subtitle }}</div>
    @endif
    @if($href)
        </a>
    @endif
    {{ $slot }}
</div>
