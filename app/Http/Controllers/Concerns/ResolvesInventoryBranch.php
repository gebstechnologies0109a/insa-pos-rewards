<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ResolvesInventoryBranch
{
    protected function resolveInventoryBranchId(Request $request): int
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return (int) $request->input('branch_id', $user->branch_id ?? 1);
        }

        if ($user->isBranchScoped()) {
            if (! $user->branch_id) {
                abort(422, 'Your account is not assigned to a branch.');
            }

            return (int) $user->branch_id;
        }

        return (int) $request->input('branch_id', $user->branch_id ?? 1);
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
