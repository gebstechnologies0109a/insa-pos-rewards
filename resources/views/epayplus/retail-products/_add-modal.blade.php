<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="{{ route('epayplus.retail-products.store') }}">
            @csrf
            <input type="hidden" name="retailer_id" value="{{ $retailerId }}">
            <div class="modal-header">
                <h5 class="modal-title">Add Shop Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @include('epayplus.retail-products._form-fields')
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">Save</button>
            </div>
        </form>
    </div>
</div>
