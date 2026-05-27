@extends('layouts.epayplus')

@section('title', $title)

@section('content')
<div class="mb-3">
    <a href="{{ route('epayplus.integrations.maya') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Maya Integration
    </a>
</div>
<div class="card border-0 shadow-sm">
    <div class="card-body prose-doc">
        {!! $html !!}
    </div>
</div>
<style>
.prose-doc h1, .prose-doc h2, .prose-doc h3 { margin-top: 1.25rem; }
.prose-doc pre { background: #f8f9fa; padding: 1rem; border-radius: 0.375rem; overflow-x: auto; }
.prose-doc code { font-size: 0.875em; }
.prose-doc table { width: 100%; margin: 1rem 0; }
.prose-doc th, .prose-doc td { border: 1px solid #dee2e6; padding: 0.5rem; }
</style>
@endsection
