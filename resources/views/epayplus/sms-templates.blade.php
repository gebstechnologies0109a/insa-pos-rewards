@extends('layouts.epayplus')
@section('title', 'SMS Configuration')

@section('content')
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="{{ route('epayplus.sms') }}">SMS Gateway</a></li>
            <li class="breadcrumb-item active">Templates & Configuration</li>
        </ol>
    </nav>
    <h4>SMS Templates & Configuration</h4>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        {{-- SMS Templates --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-medium">SMS Templates</div>
            <div class="card-body">
                <form method="POST" action="{{ route('epayplus.sms.templates.update') }}">
                    @csrf
                    <div id="templates-container">
                        @forelse($templates as $i => $tmpl)
                        <div class="border rounded p-3 mb-3 template-item">
                            <div class="mb-2">
                                <input type="text" name="templates[{{ $i }}][name]" class="form-control form-control-sm" placeholder="Template Name" value="{{ $tmpl['name'] ?? '' }}">
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col">
                                    <input type="text" name="templates[{{ $i }}][keyword]" class="form-control form-control-sm" placeholder="Keyword" value="{{ $tmpl['keyword'] ?? '' }}">
                                </div>
                                <div class="col">
                                    <input type="text" name="templates[{{ $i }}][provider]" class="form-control form-control-sm" placeholder="Provider" value="{{ $tmpl['provider'] ?? '' }}">
                                </div>
                            </div>
                            <textarea name="templates[{{ $i }}][format]" class="form-control form-control-sm" rows="2" placeholder="SMS Format (use {amount}, {number}, {product})">{{ $tmpl['format'] ?? '' }}</textarea>
                        </div>
                        @empty
                        <p class="text-muted">No templates configured. Add your first template below.</p>
                        @endforelse
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-3" onclick="addTemplate()">
                        <i class="bi bi-plus"></i> Add Template
                    </button>
                    <div>
                        <button type="submit" class="btn btn-success">Save Templates</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        {{-- SMS Providers --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-medium">SMS Providers / Gateways</div>
            <div class="card-body">
                <form method="POST" action="{{ route('epayplus.sms.providers.update') }}">
                    @csrf
                    @forelse($providers as $i => $prov)
                    <div class="border rounded p-3 mb-2">
                        <div class="row g-2">
                            <div class="col-5">
                                <input type="text" name="providers[{{ $i }}][name]" class="form-control form-control-sm" placeholder="Name" value="{{ $prov['name'] ?? '' }}">
                            </div>
                            <div class="col-4">
                                <input type="text" name="providers[{{ $i }}][number]" class="form-control form-control-sm" placeholder="Number" value="{{ $prov['number'] ?? '' }}">
                            </div>
                            <div class="col-3">
                                <input type="text" name="providers[{{ $i }}][type]" class="form-control form-control-sm" placeholder="Type" value="{{ $prov['type'] ?? '' }}">
                            </div>
                        </div>
                    </div>
                    @empty
                    <p class="text-muted">No providers configured.</p>
                    @endforelse
                    <button type="submit" class="btn btn-success btn-sm mt-2">Save Providers</button>
                </form>
            </div>
        </div>

        {{-- Routing Rules --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-medium">Keyword/Prefix Routing</div>
            <div class="card-body">
                <form method="POST" action="{{ route('epayplus.sms.routing.update') }}">
                    @csrf
                    @forelse($routing as $i => $rule)
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <input type="text" name="routing[{{ $i }}][prefix]" class="form-control form-control-sm" placeholder="Prefix" value="{{ $rule['prefix'] ?? '' }}">
                        </div>
                        <div class="col-5">
                            <input type="text" name="routing[{{ $i }}][provider]" class="form-control form-control-sm" placeholder="Provider" value="{{ $rule['provider'] ?? '' }}">
                        </div>
                        <div class="col-3">
                            <input type="number" name="routing[{{ $i }}][priority]" class="form-control form-control-sm" placeholder="Priority" value="{{ $rule['priority'] ?? 0 }}">
                        </div>
                    </div>
                    @empty
                    <p class="text-muted">No routing rules.</p>
                    @endforelse
                    <button type="submit" class="btn btn-success btn-sm mt-2">Save Routing</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let templateIdx = {{ count($templates) }};
function addTemplate() {
    const html = `<div class="border rounded p-3 mb-3 template-item">
        <div class="mb-2"><input type="text" name="templates[${templateIdx}][name]" class="form-control form-control-sm" placeholder="Template Name"></div>
        <div class="row g-2 mb-2">
            <div class="col"><input type="text" name="templates[${templateIdx}][keyword]" class="form-control form-control-sm" placeholder="Keyword"></div>
            <div class="col"><input type="text" name="templates[${templateIdx}][provider]" class="form-control form-control-sm" placeholder="Provider"></div>
        </div>
        <textarea name="templates[${templateIdx}][format]" class="form-control form-control-sm" rows="2" placeholder="SMS Format"></textarea>
    </div>`;
    document.getElementById('templates-container').insertAdjacentHTML('beforeend', html);
    templateIdx++;
}
</script>
@endpush
@endsection
