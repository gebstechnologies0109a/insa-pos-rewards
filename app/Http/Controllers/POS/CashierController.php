<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Services\POS\PosTerminalSessionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashierController extends Controller
{
    public function __construct(
        protected PosTerminalSessionService $sessions,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $branchId = (int) ($user->branch_id ?? 0);

        $licenseActive = $branchId > 0 ? $this->sessions->licenseAllowsBranch($branchId) : true;
        $maxSlots = $branchId > 0 ? $this->sessions->maxSlotsForBranch($branchId) : 1;
        $activeSessions = $branchId > 0 ? $this->sessions->activeCountForBranch($branchId) : 0;

        return view('pos.cashier.index', [
            'licenseActive'  => $licenseActive,
            'maxSlots'       => $maxSlots,
            'activeSessions' => $activeSessions,
        ]);
    }
}
