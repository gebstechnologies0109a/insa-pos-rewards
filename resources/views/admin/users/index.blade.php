@extends('layouts.backoffice')

@section('page-title', 'User Management')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Users</h1>
    <a href="{{ route('admin.users.create') }}" class="px-4 py-2 bg-blue-700 text-white rounded hover:bg-blue-800 text-sm">Add User</a>
</div>

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <form method="GET" action="{{ route('admin.users.index') }}" class="flex gap-3">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or email..."
               class="flex-1 p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
        <select name="role" class="p-2 border rounded">
            <option value="">All Roles</option>
            @foreach(\App\Models\User::ROLES as $role)
            <option value="{{ $role }}" {{ request('role') === $role ? 'selected' : '' }}>{{ ucfirst($role) }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded hover:bg-gray-800">Filter</button>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left p-3 font-medium">Name</th>
                <th class="text-left p-3 font-medium">Email</th>
                <th class="text-center p-3 font-medium">Role</th>
                <th class="text-left p-3 font-medium">Branch</th>
                <th class="text-right p-3 font-medium">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
            @php
                $roleColors = [
                    'owner' => 'bg-red-100 text-red-800',
                    'admin' => 'bg-purple-100 text-purple-800',
                    'manager' => 'bg-blue-100 text-blue-800',
                    'cashier' => 'bg-green-100 text-green-800',
                    'stockman' => 'bg-yellow-100 text-yellow-800',
                ];
            @endphp
            <tr class="border-t hover:bg-gray-50">
                <td class="p-3 font-medium">{{ $user->name }}</td>
                <td class="p-3 text-gray-600">{{ $user->email }}</td>
                <td class="p-3 text-center">
                    <span class="px-2 py-1 rounded text-xs font-medium {{ $roleColors[$user->role] ?? 'bg-gray-100 text-gray-800' }}">
                        {{ ucfirst($user->role) }}
                    </span>
                </td>
                <td class="p-3 text-gray-600">{{ $user->branch?->name ?? '—' }}</td>
                <td class="p-3 text-right">
                    @php $canEdit = !$user->isOwner() || auth()->user()->canModifyOwnerUsers(); @endphp
                    @if($canEdit)
                    <a href="{{ route('admin.users.edit', $user) }}" class="text-blue-600 hover:underline text-xs mr-2">Edit</a>
                    @endif
                    @if(!$user->isOwner() && $user->id !== auth()->id())
                        @if($user->isAdmin() && !auth()->user()->isOwner())
                        {{-- Admin can't delete other admins --}}
                        @else
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline"
                              onsubmit="return confirm('Delete this user?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline text-xs">Delete</button>
                        </form>
                        @endif
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="p-6 text-center text-gray-400">No users found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $users->links() }}</div>
@endsection
