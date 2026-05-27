@extends('layouts.super-admin')

@section('page-title', 'Add Branch')

@section('content')
<div class="max-w-xl">
    <div class="mb-6">
        <a href="{{ route('super-admin.branches.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Back to branches</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h3 class="font-semibold text-gray-900 mb-4">Create Branch</h3>
        <form method="POST" action="{{ route('super-admin.branches.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Company</label>
                <select name="company_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Select company...</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
                @error('company_id')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Branch Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Address (optional)</label>
                <input type="text" name="address" value="{{ old('address') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">Create Branch</button>
        </form>
    </div>
</div>
@endsection
