@extends('layouts.backoffice')

@section('content')
<h1 class="text-2xl font-bold mb-6">Categories</h1>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">

    <!-- ADD CATEGORY -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="font-semibold mb-4">Add Category</h2>
        <form method="POST" action="{{ route('admin.categories.store') }}" class="flex gap-3">
            @csrf
            <input type="text" name="name" required placeholder="Category name"
                   class="flex-1 p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none @error('name') border-red-500 @enderror">
            <button type="submit" class="px-4 py-2 bg-blue-700 text-white rounded hover:bg-blue-800">Add</button>
        </form>
        @error('name') <p class="text-red-600 text-xs mt-2">{{ $message }}</p> @enderror
    </div>

    <!-- CATEGORY LIST -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="font-semibold mb-4">All Categories</h2>
        @forelse($categories as $category)
        <div class="flex items-center justify-between py-2 {{ !$loop->last ? 'border-b' : '' }}">
            <div>
                <span class="font-medium">{{ $category->name }}</span>
                <span class="text-xs text-gray-400 ml-2">({{ $category->products_count }} products)</span>
            </div>
            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="flex gap-1">
                    @csrf @method('PUT')
                    <input type="text" name="name" value="{{ $category->name }}" required
                           class="w-32 p-1 text-sm border rounded">
                    <button type="submit" class="text-blue-600 text-xs hover:underline">Rename</button>
                </form>
                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="inline"
                      onsubmit="return confirm('Delete this category?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-600 text-xs hover:underline">Delete</button>
                </form>
            </div>
        </div>
        @empty
        <p class="text-gray-400 text-sm">No categories yet.</p>
        @endforelse
    </div>
</div>
@endsection
