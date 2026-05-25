<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\POS\PosShiftAudit;
use App\Models\User;
use Illuminate\Http\Request;

class ShiftAuditController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $branches = Branch::orderBy('name')->get();
        $users = User::orderBy('name')->get();

        $query = PosShiftAudit::with(['user', 'shift.branch']);

        if ($user->isManager() && $user->branch_id) {
            $query->whereHas('shift', function ($q) use ($user) {
                $q->where('branch_id', $user->branch_id);
            });
        } elseif ($request->filled('branch_id')) {
            $query->whereHas('shift', function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id);
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('shift_id', $search)
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $audits = $query->orderByDesc('created_at')->paginate(50)->withQueryString();

        $auditsJson = $audits->getCollection()->map(function ($a) {
            return [
                'id'         => $a->id,
                'shift_id'   => $a->shift_id,
                'user'       => $a->user ? $a->user->name : null,
                'action'     => $a->action,
                'details'    => $a->details,
                'created_at' => $a->created_at ? $a->created_at->toIso8601String() : null,
            ];
        });

        return view('backoffice.shifts.audit', compact('audits', 'branches', 'users', 'auditsJson'));
    }
}
