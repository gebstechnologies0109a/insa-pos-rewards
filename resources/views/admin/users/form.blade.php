@extends('layouts.backoffice')

@section('page-title', $user ? 'Edit User' : 'Add User')

@section('content')
<div class="max-w-2xl">
    <h1 class="text-2xl font-bold mb-6">{{ $user ? 'Edit User: ' . $user->name : 'Add User' }}</h1>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST"
              action="{{ $user ? route('admin.users.update', $user) : route('admin.users.store') }}">
            @csrf
            @if($user) @method('PUT') @endif

            <div class="space-y-5">
                <div>
                    <label for="name" class="block font-medium text-gray-800 mb-1">Full Name *</label>
                    <input type="text" id="name" name="name"
                           value="{{ old('name', $user?->name) }}" required
                           class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none @error('name') border-red-500 @enderror">
                    @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="block font-medium text-gray-800 mb-1">Email *</label>
                    <input type="email" id="email" name="email"
                           value="{{ old('email', $user?->email) }}" required
                           class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none @error('email') border-red-500 @enderror">
                    @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="password" class="block font-medium text-gray-800 mb-1">Password {{ $user ? '(leave blank to keep)' : '*' }}</label>
                        <input type="password" id="password" name="password"
                               {{ $user ? '' : 'required' }}
                               class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none @error('password') border-red-500 @enderror">
                        @error('password') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="block font-medium text-gray-800 mb-1">Confirm Password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation"
                               class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="role" class="block font-medium text-gray-800 mb-1">Role *</label>
                        <select id="role" name="role" required
                                class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            @foreach($roles as $role)
                            <option value="{{ $role }}" {{ old('role', $user?->role) === $role ? 'selected' : '' }}>
                                {{ ucfirst($role) }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="branch_id" class="block font-medium text-gray-800 mb-1">Branch</label>
                        <select id="branch_id" name="branch_id"
                                class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="">No Branch</option>
                            @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" {{ old('branch_id', $user?->branch_id) == $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex gap-3">
                <button type="submit" class="px-6 py-2 bg-blue-700 text-white rounded hover:bg-blue-800 font-medium">
                    {{ $user ? 'Update User' : 'Create User' }}
                </button>
                <a href="{{ route('admin.users.index') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
