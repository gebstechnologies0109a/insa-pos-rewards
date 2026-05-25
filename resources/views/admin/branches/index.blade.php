@extends('layouts.backoffice')

@section('content')
<h1 class="text-2xl font-bold mb-6">Branch Management</h1>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- ADD BRANCH -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="font-semibold mb-4">Add Branch</h2>
        <form method="POST" action="{{ route('admin.branches.store') }}" class="space-y-3">
            @csrf
            <div>
                <input type="text" name="name" required placeholder="Branch name"
                       class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <div>
                <input type="text" name="address" placeholder="Address (optional)"
                       class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <button type="submit" class="w-full px-4 py-2 bg-blue-700 text-white rounded hover:bg-blue-800">Add Branch</button>
        </form>
    </div>

    <!-- BRANCH LIST -->
    <div class="lg:col-span-2 bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left p-3 font-medium">Branch</th>
                    <th class="text-left p-3 font-medium">Address</th>
                    <th class="text-center p-3 font-medium">Users</th>
                    <th class="text-right p-3 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($branches as $branch)
                <tr class="border-t hover:bg-gray-50">
                    <td class="p-3 font-medium">{{ $branch->name }}</td>
                    <td class="p-3 text-gray-600">{{ $branch->address ?? '—' }}</td>
                    <td class="p-3 text-center">{{ $branch->users_count }}</td>
                    <td class="p-3 text-right">
                        <form method="POST" action="{{ route('admin.branches.update', $branch) }}" class="inline-flex gap-1 items-center">
                            @csrf @method('PUT')
                            <input type="text" name="name" value="{{ $branch->name }}" required class="w-28 p-1 text-xs border rounded">
                            <input type="text" name="address" value="{{ $branch->address }}" class="w-32 p-1 text-xs border rounded" placeholder="Address">
                            <button type="submit" class="text-blue-600 text-xs hover:underline">Save</button>
                        </form>
                        <form method="POST" action="{{ route('admin.branches.destroy', $branch) }}" class="inline ml-2"
                              onsubmit="return confirm('Delete this branch?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 text-xs hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="p-6 text-center text-gray-400">No branches yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($branches->count() > 0)
<div class="mt-6 bg-white rounded-lg shadow p-6">
    <h2 class="font-semibold mb-4">Assign User to Branch</h2>
    <form method="POST" action="{{ route('admin.branches.assign') }}" class="flex gap-3 items-end">
        @csrf
        <div>
            <label class="block text-xs text-gray-500 mb-1">User</label>
            <select name="user_id" required class="p-2 border rounded">
                <option value="">Select user...</option>
                @foreach(\App\Models\User::orderBy('name')->get() as $user)
                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Branch</label>
            <select name="branch_id" required class="p-2 border rounded">
                @foreach($branches as $branch)
                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-green-700 text-white rounded hover:bg-green-800">Assign</button>
    </form>
</div>
@endif
@endsection
