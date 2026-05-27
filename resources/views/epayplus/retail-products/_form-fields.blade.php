@php $p = $product ?? null; @endphp
<div class="mb-3">
    <label class="form-label">Name *</label>
    <input type="text" name="name" class="form-control" required value="{{ old('name', $p->name ?? '') }}">
</div>
<div class="mb-3">
    <label class="form-label">SKU</label>
    <input type="text" name="sku" class="form-control" value="{{ old('sku', $p->sku ?? '') }}">
</div>
<div class="row">
    <div class="col-6 mb-3">
        <label class="form-label">Price (₱) *</label>
        <input type="number" step="0.01" min="0" name="price" class="form-control" required value="{{ old('price', $p->price ?? '') }}">
    </div>
    <div class="col-6 mb-3">
        <label class="form-label">Stock *</label>
        <input type="number" min="0" name="stock" class="form-control" required value="{{ old('stock', $p->stock ?? 0) }}">
    </div>
</div>
<div class="mb-3">
    <label class="form-label">Category</label>
    <input type="text" name="category" class="form-control" placeholder="e.g. Snacks, Drinks" value="{{ old('category', $p->category ?? '') }}">
</div>
<div class="mb-3">
    <label class="form-label">Description</label>
    <textarea name="description" class="form-control" rows="2">{{ old('description', $p->description ?? '') }}</textarea>
</div>
<div class="row">
    <div class="col-6 mb-3">
        <label class="form-label">Sort order</label>
        <input type="number" min="0" name="sort_order" class="form-control" value="{{ old('sort_order', $p->sort_order ?? 0) }}">
    </div>
    <div class="col-6 mb-3 d-flex align-items-end">
        <div class="form-check">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="active_{{ $p->id ?? 'new' }}" @checked(old('is_active', $p->is_active ?? true))>
            <label class="form-check-label" for="active_{{ $p->id ?? 'new' }}">Active</label>
        </div>
    </div>
</div>
