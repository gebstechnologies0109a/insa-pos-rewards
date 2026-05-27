<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Services\POS\PosTerminalSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosTerminalSessionController extends Controller
{
    public function __construct(
        protected PosTerminalSessionService $sessions,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_fingerprint' => 'required|string|max:128',
            'session_id'         => 'nullable|uuid',
            'branch_id'          => 'nullable|integer',
        ]);

        $user = $request->user();
        $branchId = (int) ($data['branch_id'] ?? $user->branch_id ?? 0);

        if ($branchId < 1) {
            return response()->json([
                'success' => false,
                'message' => 'No branch assigned to this user.',
            ], 422);
        }

        if (! $user->isSuperAdmin() && (int) $user->branch_id !== $branchId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized branch.'], 403);
        }

        $result = $this->sessions->registerOrResume(
            $branchId,
            (int) $user->id,
            $data['device_fingerprint'],
            $data['session_id'] ?? null,
        );

        if (! $result['success']) {
            $status = ($result['code'] ?? '') === 'license_limit_reached' ? 403 : 422;

            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'code'    => $result['code'] ?? 'error',
            ], $status);
        }

        $session = $result['session'];

        return response()->json([
            'success'    => true,
            'resumed'    => (bool) ($result['resumed'] ?? false),
            'session_id' => $session->id,
            'slots'      => [
                'max'    => $this->sessions->maxSlotsForBranch($branchId),
                'active' => $this->sessions->activeCountForBranch($branchId),
            ],
        ]);
    }

    public function end(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id'         => 'required|uuid',
            'device_fingerprint' => 'nullable|string|max:128',
        ]);

        $ended = $this->sessions->endSession(
            $data['session_id'],
            $data['device_fingerprint'] ?? null,
        );

        return response()->json(['success' => $ended]);
    }

    public function status(Request $request): JsonResponse
    {
        $branchId = (int) $request->input('branch_id', $request->user()->branch_id ?? 0);

        if ($branchId < 1) {
            return response()->json(['success' => false, 'message' => 'Branch required.'], 422);
        }

        return response()->json([
            'success' => true,
            'slots'   => [
                'max'    => $this->sessions->maxSlotsForBranch($branchId),
                'active' => $this->sessions->activeCountForBranch($branchId),
            ],
            'license_active' => $this->sessions->licenseAllowsBranch($branchId),
        ]);
    }
}
