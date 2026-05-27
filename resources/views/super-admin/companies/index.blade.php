@extends('layouts.super-admin')

@section('page-title', 'Companies')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h3 class="font-semibold text-gray-900 text-lg">Companies</h3>
        <p class="text-sm text-gray-500 mt-1">Top-level organization for branches and devices.</p>
    </div>
    <a href="{{ route('super-admin.companies.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
        Add Company
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-6 py-3 font-medium text-gray-600">Company</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">Branches</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">Status</th>
                    <th class="text-right px-6 py-3 font-medium text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($companies as $company)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 font-medium text-gray-900">{{ $company->name }}</td>
                    <td class="px-6 py-3 text-center">{{ $company->branches_count }}</td>
                    <td class="px-6 py-3 text-center">
                        @if($company->isActive())
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Inactive</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-right">
                        <a href="{{ route('super-admin.companies.edit', $company) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Edit</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-8 text-center text-gray-500">No companies found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
