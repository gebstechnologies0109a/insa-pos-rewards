<div class="modal fade" id="editModal{{ $product->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="{{ route('epayplus.retail-products.update', $product) }}">
            @csrf @method('PUT')
            <input type="hidden" name="retailer_id" value="{{ $retailerId }}">
            <div class="modal-header">
                <h5 class="modal-title">Edit — {{ $product->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @include('epayplus.retail-products._form-fields', ['product' => $product])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Update</button>
            </div>
        </form>
    </div>
</div>
