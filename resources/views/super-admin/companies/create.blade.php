@extends('layouts.super-admin')

@section('page-title', 'Add Company')

@section('content')
<div class="max-w-xl">
    <div class="mb-6">
        <a href="{{ route('super-admin.companies.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Back to companies</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h3 class="font-semibold text-gray-900 mb-4">Create Company</h3>
        <form method="POST" action="{{ route('super-admin.companies.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Company Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                <select name="status" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                    <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                </select>
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">Create Company</button>
        </form>
    </div>
</div>
@endsection
