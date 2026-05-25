@props(['data' => [], 'id' => null])

@php $viewerId = $id ?? 'jv-' . uniqid(); @endphp

<div class="json-viewer relative" id="{{ $viewerId }}">
    <div class="flex justify-end mb-1 gap-1">
        <button type="button" data-toggle-btn onclick="jsonViewerToggle('{{ $viewerId }}')"
                class="text-xs px-2 py-1 bg-gray-200 rounded hover:bg-gray-300 text-gray-700">Collapse</button>
        <button type="button" data-copy-btn onclick="jsonViewerCopy('{{ $viewerId }}')"
                class="text-xs px-2 py-1 bg-gray-200 rounded hover:bg-gray-300 text-gray-700">Copy</button>
    </div>
    <pre class="bg-gray-900 text-gray-100 p-3 rounded text-xs font-mono overflow-x-auto max-h-80 overflow-y-auto leading-relaxed"><code id="{{ $viewerId }}-code"></code></pre>
</div>

<script src="{{ asset('js/json-viewer.js') }}"></script>
<script>
(function () {
    var raw = @json($data);
    jsonViewerRender('{{ $viewerId }}-code', raw);
})();
</script>

<style>
    .jv-key { color: #c4b5fd; }
    .jv-string { color: #86efac; }
    .jv-number { color: #93c5fd; }
    .jv-bool { color: #fde047; }
    .jv-null { color: #9ca3af; }
</style>
