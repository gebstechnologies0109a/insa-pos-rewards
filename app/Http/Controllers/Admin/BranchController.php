<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::withCount('users')->orderBy('name')->get();

        return view('admin.branches.index', compact('branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
        ]);

        Branch::create($data);

        return redirect()->route('admin.branches.index')
            ->with('success', 'Branch created.');
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
        ]);

        $branch->update($data);

        return redirect()->route('admin.branches.index')
            ->with('success', 'Branch updated.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $branch->users()->update(['branch_id' => null]);
        $branch->delete();

        return redirect()->route('admin.branches.index')
            ->with('success', 'Branch deleted.');
    }

    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id'   => 'required|exists:users,id',
            'branch_id' => 'required|exists:branches,id',
        ]);

        User::where('id', $data['user_id'])->update(['branch_id' => $data['branch_id']]);

        return redirect()->route('admin.branches.index')
            ->with('success', 'User assigned to branch.');
    }
}
