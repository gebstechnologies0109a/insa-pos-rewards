<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\POS\PosLicense;
use App\Models\POS\PosSale;
use App\Models\POS\PosShift;

class DashboardController extends Controller
{
    public function index()
    {
        $totalBranches = Branch::count();
        $totalLicenses = PosLicense::where('active', true)->count();
        $totalSlots = PosLicense::where('active', true)->sum('pos_slots');
        $activeShifts = PosShift::where('status', 'open')->count();
        $totalSalesToday = PosSale::whereDate('created_at', today())->count();
        $revenueToday = PosSale::whereDate('created_at', today())->sum('total');

        $branches = Branch::withCount(['openShifts'])
            ->with('license')
            ->get();

        return view('super-admin.dashboard', compact(
            'totalBranches',
            'totalLicenses',
            'totalSlots',
            'activeShifts',
            'totalSalesToday',
            'revenueToday',
            'branches',
        ));
    }
}
