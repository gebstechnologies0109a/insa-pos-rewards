<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\POS\PosLicense;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function index()
    {
        $branches = Branch::with('license')
            ->withCount(['openShifts', 'activeTerminalSessions'])
            ->orderBy('name')
            ->get();

        return view('super-admin.licenses.index', compact('branches'));
    }

    public function update(Request $request, Branch $branch)
    {
        $request->validate([
            'pos_slots' => 'required|integer|min:1|max:100',
            'active'    => 'required|boolean',
        ]);

        $active = $request->boolean('active');

        $license = PosLicense::updateOrCreate(
            ['branch_id' => $branch->id],
            [
                'pos_slots' => (int) $request->pos_slots,
                'active'    => $active,
                'status'    => $active ? PosLicense::STATUS_ACTIVE : PosLicense::STATUS_SUSPENDED,
            ]
        );

        return redirect()->route('super-admin.licenses.index')
            ->with('success', "License for {$branch->name} updated — {$license->pos_slots} slot(s), " . ($license->active ? 'active' : 'inactive') . '.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'pos_slots' => 'required|integer|min:1|max:100',
        ]);

        PosLicense::updateOrCreate(
            ['branch_id' => $request->branch_id],
            [
                'pos_slots' => (int) $request->pos_slots,
                'active'    => true,
                'status'    => PosLicense::STATUS_ACTIVE,
            ]
        );

        return redirect()->route('super-admin.licenses.index')
            ->with('success', 'License created successfully.');
    }
}
