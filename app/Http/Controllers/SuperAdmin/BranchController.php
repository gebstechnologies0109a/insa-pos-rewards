<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\POS\Company;
use App\Models\POS\Device;
use App\Models\POS\PosSale;
use App\Models\POS\PosShift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        $branches = Branch::with('company')
            ->withCount(['users', 'openShifts', 'devices'])
            ->with('license')
            ->orderBy('name')
            ->get();

        return view('super-admin.branches.index', compact('branches'));
    }

    public function create(): View
    {
        $companies = Company::orderBy('name')->get();

        return view('super-admin.branches.create', compact('companies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name'       => 'required|string|max:255',
            'address'    => 'nullable|string|max:500',
        ]);

        Branch::create($data);

        return redirect()->route('super-admin.branches.index')
            ->with('success', 'Branch created successfully.');
    }

    public function show(Branch $branch): View
    {
        $branch->load('company', 'license', 'users', 'devices');

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

    public function edit(Branch $branch): View
    {
        $companies = Company::orderBy('name')->get();

        return view('super-admin.branches.edit', compact('branch', 'companies'));
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name'       => 'required|string|max:255',
            'address'    => 'nullable|string|max:500',
        ]);

        $branch->update($data);

        return redirect()->route('super-admin.branches.index')
            ->with('success', 'Branch updated successfully.');
    }
}
