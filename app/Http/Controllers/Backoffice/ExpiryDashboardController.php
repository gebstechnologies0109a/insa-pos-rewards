<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Concerns\ResolvesInventoryBranch;
use App\Http\Controllers\Controller;
use App\Models\Inventory\ExpiryAlert;
use App\Models\POS\Branch;
use App\Services\Inventory\InventoryForecastService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ExpiryDashboardController extends Controller
{
    use ResolvesInventoryBranch;

    public function __construct(
        protected InventoryForecastService $forecast,
    ) {}

    public function index(Request $request)
    {
        $branchId = $this->resolveInventoryBranchId($request);
        $this->authorizeInventoryBranch($branchId);

        $filter = $request->input('bucket', 'active');
        $slowMoving = collect();
        $alerts = new LengthAwarePaginator([], 0, 50);
        $counts = [
            'thirty_day'  => 0,
            'seven_day'   => 0,
            'expired'     => 0,
            'slow_moving' => 0,
        ];
        $migrationPending = ! Schema::hasTable('expiry_alerts');

        $branches = auth()->user()->isBranchScoped()
            ? collect()
            : Branch::orderBy('name')->get();

        if ($migrationPending) {
            return view('backoffice.inventory.report-expiry', compact(
                'alerts',
                'slowMoving',
                'branches',
                'branchId',
                'counts',
                'filter',
                'migrationPending',
            ));
        }

        try {
            if ($filter === 'slow_moving') {
                $slowMoving = collect($this->forecast->slowMovingProducts($branchId));
            } else {
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
            }

            $counts = [
                'thirty_day'  => ExpiryAlert::forBranch($branchId)->active()->where('alert_type', ExpiryAlert::TYPE_THIRTY_DAY)->count(),
                'seven_day'   => ExpiryAlert::forBranch($branchId)->active()->where('alert_type', ExpiryAlert::TYPE_SEVEN_DAY)->count(),
                'expired'     => ExpiryAlert::forBranch($branchId)->active()->where('alert_type', ExpiryAlert::TYPE_EXPIRED)->count(),
                'slow_moving' => count($this->forecast->slowMovingProducts($branchId)),
            ];
        } catch (Throwable $e) {
            Log::error('Expiry dashboard failed', [
                'branch_id' => $branchId,
                'filter'    => $filter,
                'message'   => $e->getMessage(),
            ]);

            return view('backoffice.inventory.report-expiry', compact(
                'alerts',
                'slowMoving',
                'branches',
                'branchId',
                'counts',
                'filter',
                'migrationPending',
            ))->with('error', 'Could not load expiry data. Check that inventory migrations have been run.');
        }

        return view('backoffice.inventory.report-expiry', compact(
            'alerts',
            'slowMoving',
            'branches',
            'branchId',
            'counts',
            'filter',
            'migrationPending',
        ));
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
