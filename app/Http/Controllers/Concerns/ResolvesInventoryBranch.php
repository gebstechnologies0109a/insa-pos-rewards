<?php

namespace App\Http\Controllers\Concerns;

use App\Models\POS\Branch;
use Illuminate\Http\Request;

trait ResolvesInventoryBranch
{
    protected function resolveInventoryBranchId(Request $request): int
    {
        $user = auth()->user();

        if ($request->filled('branch_id')) {
            return (int) $request->input('branch_id');
        }

        if ($user->branch_id) {
            return (int) $user->branch_id;
        }

        if ($user->isBranchScoped()) {
            abort(422, 'Your account is not assigned to a branch.');
        }

        $fallback = Branch::orderBy('name')->value('id');

        if (! $fallback) {
            abort(422, 'No branches are configured. Create a branch first.');
        }

        return (int) $fallback;
    }

    protected function authorizeInventoryBranch(int $branchId): void
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        if ($user->isBranchScoped() && (int) $user->branch_id !== $branchId) {
            abort(403, 'Unauthorized branch access.');
        }
    }
}
