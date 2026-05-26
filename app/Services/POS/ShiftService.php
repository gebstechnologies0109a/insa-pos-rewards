<?php

namespace App\Services\POS;

use App\Models\POS\Branch;
use App\Models\POS\PosSale;
use App\Models\POS\PosShift;
use App\Models\POS\PosShiftAudit;
use App\Models\POS\ShiftAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class ShiftService
{
    public function getActiveShift(User $user): ?PosShift
    {
        return PosShift::where('user_id', $user->id)
            ->where('status', 'open')
            ->first();
    }

    public function openShift(User $user, float $openingCash, ?Request $request = null): PosShift
    {
        if ($this->getActiveShift($user)) {
            throw new \Exception('You already have an active shift.');
        }

        $this->enforceLicenseSlots($user);

        $shift = PosShift::create([
            'branch_id'    => $user->branch_id,
            'user_id'      => $user->id,
            'opened_at'    => now(),
            'opening_cash' => $openingCash,
            'status'       => 'open',
        ]);

        $this->logAudit($shift, $user, 'open', [
            'opening_cash' => $openingCash,
            'branch_id'    => $user->branch_id,
            'ip'           => $request?->ip(),
            'user_agent'   => $request?->userAgent(),
        ]);

        $this->logAuditDiff($shift, $user, 'open_shift', null, [
            'branch_id'    => $user->branch_id,
            'user_id'      => $user->id,
            'opened_at'    => (string) $shift->opened_at,
            'opening_cash' => $openingCash,
            'status'       => 'open',
        ]);

        return $shift;
    }

    protected function enforceLicenseSlots(User $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        $branch = Branch::find($user->branch_id);

        if (! $branch) {
            return;
        }

        $maxSlots = $branch->getPosSlots();
        $openShifts = PosShift::where('branch_id', $branch->id)
            ->where('status', 'open')
            ->count();

        if ($openShifts >= $maxSlots) {
            throw new \Exception(
                'Maximum number of active POS registers reached for this store. Please upgrade your subscription to add another POS.'
            );
        }
    }

    public function closeShift(PosShift $shift, float $closingCash, ?Request $request = null): PosShift
    {
        if (! $shift->isOpen()) {
            throw new \Exception('This shift is already closed.');
        }

        $systemSales = PosSale::where('shift_id', $shift->id)->sum('total');

        $expectedCash = $shift->opening_cash + $systemSales;
        $variance = $closingCash - $expectedCash;

        $shift->update([
            'closed_at'          => now(),
            'closing_cash'       => $closingCash,
            'system_sales_total' => $systemSales,
            'cash_variance'      => $variance,
            'status'             => 'closed',
        ]);

        $shift = $shift->fresh();

        $closingUser = auth()->user() ?? $shift->user;

        $this->logAudit($shift, $closingUser, 'close', [
            'opening_cash'       => (float) $shift->opening_cash,
            'closing_cash'       => $closingCash,
            'system_sales_total' => (float) $systemSales,
            'expected_cash'      => (float) $expectedCash,
            'cash_variance'      => (float) $variance,
            'ip'                 => $request?->ip(),
            'user_agent'         => $request?->userAgent(),
        ]);

        $this->logAuditDiff($shift, $closingUser, 'close_shift', [
            'status'       => 'open',
            'closed_at'    => null,
            'closing_cash' => null,
        ], [
            'status'             => 'closed',
            'closed_at'          => (string) $shift->closed_at,
            'closing_cash'       => $closingCash,
            'system_sales_total' => (float) $systemSales,
            'cash_variance'      => (float) $variance,
        ]);

        return $shift;
    }

    protected function logAudit(PosShift $shift, User $user, string $action, array $details = []): void
    {
        PosShiftAudit::create([
            'shift_id'   => $shift->id,
            'user_id'    => $user->id,
            'action'     => $action,
            'details'    => $details,
            'created_at' => now(),
        ]);
    }

    protected function logAuditDiff(PosShift $shift, User $user, string $action, ?array $oldValues, ?array $newValues): void
    {
        ShiftAuditLog::create([
            'shift_id'   => $shift->id,
            'user_id'    => $user->id,
            'action'     => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
