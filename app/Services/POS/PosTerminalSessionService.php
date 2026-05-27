<?php

namespace App\Services\POS;

use App\Models\POS\Branch;
use App\Models\POS\PosLicense;
use App\Models\POS\PosTerminalSession;
use Illuminate\Support\Str;

class PosTerminalSessionService
{
    public function activeCountForBranch(int $branchId): int
    {
        return PosTerminalSession::forBranch($branchId)->active()->count();
    }

    public function findActiveByFingerprint(int $branchId, string $fingerprint): ?PosTerminalSession
    {
        return PosTerminalSession::forBranch($branchId)
            ->active()
            ->where('device_fingerprint', $fingerprint)
            ->first();
    }

    public function findActiveById(string $sessionId): ?PosTerminalSession
    {
        return PosTerminalSession::where('id', $sessionId)->active()->first();
    }

    /**
     * @return array{success: bool, session?: PosTerminalSession, message?: string, code?: string}
     */
    public function registerOrResume(int $branchId, int $userId, string $fingerprint, ?string $existingSessionId = null): array
    {
        $fingerprint = trim($fingerprint);
        if ($fingerprint === '') {
            return ['success' => false, 'message' => 'Device fingerprint is required.', 'code' => 'invalid_fingerprint'];
        }

        if (! $this->licenseAllowsBranch($branchId)) {
            return [
                'success' => false,
                'message' => 'Branch license is inactive or expired.',
                'code'    => 'license_inactive',
            ];
        }

        if ($existingSessionId) {
            $existing = $this->findActiveById($existingSessionId);
            if ($existing && $existing->branch_id === $branchId && $existing->device_fingerprint === $fingerprint) {
                $existing->touch();

                return ['success' => true, 'session' => $existing, 'resumed' => true];
            }
        }

        $byFingerprint = $this->findActiveByFingerprint($branchId, $fingerprint);
        if ($byFingerprint) {
            if ($byFingerprint->user_id !== $userId) {
                $byFingerprint->update([
                    'user_id' => $userId,
                ]);
            }
            $byFingerprint->touch();

            return ['success' => true, 'session' => $byFingerprint->fresh(), 'resumed' => true];
        }

        $slots = $this->maxSlotsForBranch($branchId);
        if ($this->activeCountForBranch($branchId) >= $slots) {
            return [
                'success' => false,
                'message' => 'License limit reached. All cashier seats for this branch are in use. Close another session or contact your administrator.',
                'code'    => 'license_limit_reached',
            ];
        }

        $session = PosTerminalSession::create([
            'id'                 => (string) Str::uuid(),
            'branch_id'          => $branchId,
            'user_id'            => $userId,
            'device_fingerprint' => $fingerprint,
            'started_at'         => now(),
            'is_active'          => true,
        ]);

        return ['success' => true, 'session' => $session, 'resumed' => false];
    }

    public function endSession(string $sessionId, ?string $fingerprint = null): bool
    {
        $session = PosTerminalSession::where('id', $sessionId)->active()->first();
        if (! $session) {
            return false;
        }

        if ($fingerprint !== null && $session->device_fingerprint !== $fingerprint) {
            return false;
        }

        $session->update([
            'is_active' => false,
            'ended_at'  => now(),
        ]);

        return true;
    }

    public function maxSlotsForBranch(int $branchId): int
    {
        $branch = Branch::with('license')->find($branchId);

        return $branch ? $branch->getPosSlots() : 1;
    }

    public function licenseAllowsBranch(int $branchId): bool
    {
        $license = PosLicense::where('branch_id', $branchId)->first();

        if (! $license) {
            return true;
        }

        return $license->isCurrentlyActive();
    }

    /**
     * @return \Illuminate\Support\Collection<int, PosTerminalSession>
     */
    public function activeSessionsForBranch(?int $branchId = null)
    {
        $query = PosTerminalSession::with(['user:id,name,email', 'branch:id,name'])
            ->active()
            ->orderByDesc('started_at');

        if ($branchId !== null) {
            $query->forBranch($branchId);
        }

        return $query->get();
    }
}
