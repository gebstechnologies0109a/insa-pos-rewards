@extends('layouts.backoffice')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">{{ $product ? 'Edit Product' : 'Add Product' }}</h1>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST"
              action="{{ $product ? route('admin.products.update', $product) : route('admin.products.store') }}">
            @csrf
            @if($product) @method('PUT') @endif

            <div class="space-y-5">
                <div>
                    <label for="name" class="block font-medium text-gray-800 mb-1">Product Name *</label>
                    <input type="text" id="name" name="name"
                           value="{{ old('name', $product?->name) }}"
                           required
                           class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none @error('name') border-red-500 @enderror">
                    @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="sku" class="block font-medium text-gray-800 mb-1">SKU</label>
                        <input type="text" id="sku" name="sku"
                               value="{{ old('sku', $product?->sku) }}"
                               class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none @error('sku') border-red-500 @enderror">
                        @error('sku') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="barcode" class="block font-medium text-gray-800 mb-1">Barcode</label>
                        <input type="text" id="barcode" name="barcode"
                               value="{{ old('barcode', $product?->barcode) }}"
                               class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none @error('barcode') border-red-500 @enderror">
                        @error('barcode') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="price" class="block font-medium text-gray-800 mb-1">Price *</label>
                        <input type="number" id="price" name="price"
                               value="{{ old('price', $product?->price) }}"
                               step="0.01" min="0" required
                               class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none @error('price') border-red-500 @enderror">
                        @error('price') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="category_id" class="block font-medium text-gray-800 mb-1">Category</label>
                        <select id="category_id" name="category_id"
                                class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="">No Category</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('category_id', $product?->category_id) == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" id="active" name="active" value="1"
                           {{ old('active', $product?->active ?? true) ? 'checked' : '' }}
                           class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <label for="active" class="font-medium text-gray-800">Active</label>
                </div>
            </div>

            <div class="mt-8 flex gap-3">
                <button type="submit" class="px-6 py-2 bg-blue-700 text-white rounded hover:bg-blue-800 font-medium">
                    {{ $product ? 'Update Product' : 'Create Product' }}
                </button>
                <a href="{{ route('admin.products.index') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
