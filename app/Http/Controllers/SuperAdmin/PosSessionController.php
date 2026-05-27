<?php

namespace App\Http\Controllers\SuperAdmin;

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

    public function index(): View
    {
        $sessions = $this->sessions->activeSessionsForBranch();

        return view('super-admin.sessions.index', compact('sessions'));
    }

    public function end(PosTerminalSession $session): RedirectResponse
    {
        $this->sessions->endSession($session->id);

        return redirect()->route('super-admin.sessions.index')
            ->with('success', 'Terminal session ended.');
    }
}
