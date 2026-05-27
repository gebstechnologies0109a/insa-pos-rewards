<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\POS\PosTerminalSession;
use App\Services\POS\PosTerminalSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosSessionController extends Controller
{
    public function __construct(
        protected PosTerminalSessionService $sessions,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->hasPermission('license.sessions.view'), 403);

        $branchId = (int) $user->branch_id;
        $sessions = $this->sessions->activeSessionsForBranch($branchId > 0 ? $branchId : null);
        $maxSlots = $branchId > 0 ? $this->sessions->maxSlotsForBranch($branchId) : 0;

        return view('admin.pos-sessions.index', compact('sessions', 'maxSlots', 'branchId'));
    }

    public function end(Request $request, PosTerminalSession $session): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission('license.sessions.view'), 403);

        if ($user->isBranchScoped() && (int) $session->branch_id !== (int) $user->branch_id) {
            abort(403);
        }

        $this->sessions->endSession($session->id);

        return redirect()->route('admin.pos-sessions.index')
            ->with('success', 'Terminal session ended.');
    }
}
