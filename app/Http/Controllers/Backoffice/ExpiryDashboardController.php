<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Concerns\ResolvesInventoryBranch;
use App\Http\Controllers\Controller;
use App\Models\Inventory\ExpiryAlert;
use App\Models\POS\Branch;
use Illuminate\Http\Request;

class ExpiryDashboardController extends Controller
{
    use ResolvesInventoryBranch;

    public function index(Request $request)
    {
        $branchId = $this->resolveInventoryBranchId($request);
        $this->authorizeInventoryBranch($branchId);

        $filter = $request->input('bucket', 'active');

        $query = ExpiryAlert::with(['product', 'batch'])
            ->where('branch_id', $branchId);

        if ($filter === 'handled') {
            $query->whereNotNull('handled_at');
        } elseif ($filter === 'snoozed') {
            $query->whereNull('handled_at')
                ->whereNotNull('snoozed_until')
                ->where('snoozed_until', '>', now());
        } else {
            $query->active();
        }

        if ($type = $request->input('alert_type')) {
            $query->where('alert_type', $type);
        }

        $alerts = $query->orderBy('expiry_date')->paginate(50)->withQueryString();

        $counts = [
            'thirty_day' => ExpiryAlert::forBranch($branchId)->active()->where('alert_type', ExpiryAlert::TYPE_THIRTY_DAY)->count(),
            'seven_day'  => ExpiryAlert::forBranch($branchId)->active()->where('alert_type', ExpiryAlert::TYPE_SEVEN_DAY)->count(),
            'expired'    => ExpiryAlert::forBranch($branchId)->active()->where('alert_type', ExpiryAlert::TYPE_EXPIRED)->count(),
        ];

        $branches = auth()->user()->isBranchScoped()
            ? collect()
            : Branch::orderBy('name')->get();

        return view('backoffice.inventory.report-expiry', compact('alerts', 'branches', 'branchId', 'counts', 'filter'));
    }

    public function handle(ExpiryAlert $alert)
    {
        $this->authorizeInventoryBranch((int) $alert->branch_id);
        $alert->update(['handled_at' => now(), 'snoozed_until' => null]);

        return back()->with('success', 'Alert marked as handled.');
    }

    public function snooze(Request $request, ExpiryAlert $alert)
    {
        $this->authorizeInventoryBranch((int) $alert->branch_id);

        $data = $request->validate([
            'days' => 'required|integer|min:1|max:90',
        ]);

        $alert->update([
            'snoozed_until' => now()->addDays((int) $data['days']),
            'handled_at'    => null,
        ]);

        return back()->with('success', 'Alert snoozed.');
    }
}
