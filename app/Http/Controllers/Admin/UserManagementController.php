<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('branch');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        $users = $query->orderBy('name')->paginate(20)->withQueryString();
        $branches = Branch::orderBy('name')->get();

        return view('admin.users.index', compact('users', 'branches'));
    }

    public function create()
    {
        $branches = Branch::orderBy('name')->get();
        $roles = $this->allowedRoles();

        return view('admin.users.form', [
            'user'     => null,
            'branches' => $branches,
            'roles'    => $roles,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:6|confirmed',
            'role'      => 'required|in:' . implode(',', $this->allowedRoles()),
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $data['password'] = Hash::make($data['password']);

        User::create($data);

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $this->authorizeUserEdit($user);

        $branches = Branch::orderBy('name')->get();
        $roles = $this->allowedRoles();

        return view('admin.users.form', compact('user', 'branches', 'roles'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUserEdit($user);

        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => "required|email|unique:users,email,{$user->id}",
            'password'  => 'nullable|string|min:6|confirmed',
            'role'      => 'required|in:' . implode(',', $this->allowedRoles()),
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->isOwner()) {
            abort(403, 'Owner account cannot be deleted.');
        }

        if ($user->isAdmin() && ! auth()->user()->isOwner()) {
            abort(403, 'Only owner can delete admin accounts.');
        }

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted.');
    }

    protected function authorizeUserEdit(User $user): void
    {
        if ($user->isOwner() && ! auth()->user()->isOwner()) {
            abort(403, 'Only owner can modify owner accounts.');
        }

        if ($user->isAdmin() && ! auth()->user()->hasRole('owner', 'admin')) {
            abort(403, 'Unauthorized.');
        }
    }

    protected function allowedRoles(): array
    {
        $currentUser = auth()->user();

        if ($currentUser->isOwner()) {
            return User::ROLES;
        }

        // Admins can create everything except owner
        return array_values(array_diff(User::ROLES, [User::ROLE_OWNER]));
    }
}
