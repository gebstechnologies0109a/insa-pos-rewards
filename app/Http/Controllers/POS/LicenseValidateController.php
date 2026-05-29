<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\POS\Device;
use App\Services\POS\PosSettingsService;
use App\Services\POS\PosTerminalSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseValidateController extends Controller
{
    public function __construct(
        protected PosTerminalSessionService $sessions,
        protected PosSettingsService $settings,
    ) {}

    /**
     * Validate device license for a branch (fingerprint + slot check).
     */
    public function validate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_fingerprint' => 'required|string|max:128',
            'branch_id'          => 'nullable|integer',
            'terminal_session_id'=> 'nullable|uuid',
        ]);

        $user = $request->user();
        $branchId = (int) ($data['branch_id'] ?? $user->branch_id ?? 0);
        $fingerprint = trim($data['device_fingerprint']);

        if ($branchId < 1) {
            return response()->json([
                'allowed' => false,
                'message' => 'Branch required.',
                'code'    => 'branch_required',
            ], 422);
        }

        if (! $user->isSuperAdmin() && (int) $user->branch_id !== $branchId) {
            return response()->json([
                'allowed' => false,
                'message' => 'Unauthorized branch.',
                'code'    => 'unauthorized_branch',
            ], 403);
        }

        $licenseActive = $this->sessions->licenseAllowsBranch($branchId);
        if (! $licenseActive) {
            return response()->json([
                'allowed' => false,
                'message' => 'Branch license is inactive or expired.',
                'code'    => 'license_inactive',
            ], 403);
        }

        $branch = Branch::with(['company', 'license'])->find($branchId);
        $device = Device::query()
            ->where('branch_id', $branchId)
            ->where('device_fingerprint', $fingerprint)
            ->first();

        $maxSlots = $this->sessions->maxSlotsForBranch($branchId);
        $activeSlots = $this->sessions->activeCountForBranch($branchId);
        $existingSession = $this->sessions->findActiveByFingerprint($branchId, $fingerprint);
        $hasSeat = $existingSession !== null || $activeSlots < $maxSlots;

        return response()->json([
            'allowed'            => $hasSeat,
            'branch_id'          => $branchId,
            'company_id'         => $branch?->company_id,
            'company_name'       => $branch?->company?->name,
            'branch_name'        => $branch?->name,
            'device_id'          => $device?->id,
            'device_registered'  => $device !== null,
            'license_active'     => $licenseActive,
            'slots'              => [
                'max'    => $maxSlots,
                'active' => $activeSlots,
                'available' => max(0, $maxSlots - $activeSlots),
            ],
            'pos_settings'       => array_merge(
                $this->settings->all('rewards'),
                $this->settings->all('overrides'),
            ),
            'validated_at'       => now()->toIso8601String(),
            'code'               => $hasSeat ? 'ok' : 'license_limit_reached',
            'message'            => $hasSeat
                ? 'License valid.'
                : 'License limit reached. All cashier seats for this branch are in use.',
        ], $hasSeat ? 200 : 403);
    }
}
