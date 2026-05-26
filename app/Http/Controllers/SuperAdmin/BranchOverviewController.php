<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\POS\PosSale;
use App\Models\POS\PosShift;
use App\Models\User;

class BranchOverviewController extends Controller
{
    public function index()
    {
        $branches = Branch::withCount(['users', 'openShifts'])
            ->with('license')
            ->get();

        return view('super-admin.branches.index', compact('branches'));
    }

    public function show(Branch $branch)
    {
        $branch->load('license', 'users');

        $recentShifts = PosShift::where('branch_id', $branch->id)
            ->with('user')
            ->orderByDesc('opened_at')
            ->limit(20)
            ->get();

        $openShiftCount = PosShift::where('branch_id', $branch->id)
            ->where('status', 'open')
            ->count();

        $totalSalesToday = PosSale::whereHas('shift', fn ($q) => $q->where('branch_id', $branch->id))
            ->whereDate('created_at', today())
            ->count();

        $revenueToday = PosSale::whereHas('shift', fn ($q) => $q->where('branch_id', $branch->id))
            ->whereDate('created_at', today())
            ->sum('total');

        return view('super-admin.branches.show', compact(
            'branch',
            'recentShifts',
            'openShiftCount',
            'totalSalesToday',
            'revenueToday',
        ));
    }
}
