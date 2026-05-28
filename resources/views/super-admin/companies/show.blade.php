@extends('layouts.super-admin')

@section('page-title', $company->name . ' — Company')

@section('content')
<div class="mb-4">
    <a href="{{ route('super-admin.companies.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Back to Companies</a>
</div>

<div class="flex items-center justify-between mb-6">
    <div>
        <h3 class="font-semibold text-gray-900 text-lg">{{ $company->name }}</h3>
        <p class="text-sm text-gray-500 mt-1">
            @if($company->isActive())
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
            @else
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Inactive</span>
            @endif
        </p>
    </div>
    <a href="{{ route('super-admin.companies.edit', $company) }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">Edit Company</a>
</div>

<div class="bg-white rounded-xl shadow-sm border">
    <div class="px-6 py-4 border-b flex items-center justify-between">
        <h3 class="font-semibold text-gray-900">Branches ({{ $company->branches->count() }})</h3>
        <a href="{{ route('super-admin.branches.create') }}" class="text-xs text-indigo-600 hover:underline">Add branch</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-6 py-3 font-medium text-gray-600">Branch</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">Users</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">Devices</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">POS Slots</th>
                    <th class="text-center px-6 py-3 font-medium text-gray-600">Open Shifts</th>
                    <th class="text-right px-6 py-3 font-medium text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($company->branches as $branch)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 font-medium text-gray-900">{{ $branch->name }}</td>
                    <td class="px-6 py-3 text-center">{{ $branch->users_count }}</td>
                    <td class="px-6 py-3 text-center">{{ $branch->devices_count }}</td>
                    <td class="px-6 py-3 text-center">{{ $branch->license?->pos_slots ?? 1 }}</td>
                    <td class="px-6 py-3 text-center">{{ $branch->open_shifts_count }}</td>
                    <td class="px-6 py-3 text-right">
                        <a href="{{ route('super-admin.branches.show', $branch) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Details</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">No branches under this company.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
