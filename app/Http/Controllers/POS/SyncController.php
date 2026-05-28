<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\Inventory\ExpiryAlert;
use App\Models\Inventory\InventoryBatch;
use App\Models\POS\Category;
use App\Models\POS\Customer;
use App\Models\POS\Device;
use App\Models\POS\PosSale;
use App\Models\POS\Product;
use App\Services\Inventory\InventoryService;
use App\Services\POS\PosSaleService;
use App\Services\POS\PosSettingsService;
use App\Services\POS\SyncConflictResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(
        protected PosSaleService $saleService,
        protected InventoryService $inventory,
        protected SyncConflictResolver $conflicts,
        protected PosSettingsService $settings,
    ) {}

    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'time'   => now()->toIso8601String(),
        ]);
    }

    /**
     * Push local sales (idempotent via local_id). Optional device_fingerprint resolves branch/device.
     */
    public function push(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_fingerprint' => 'nullable|string|max:128',
            'local_id'           => 'required_without:sales|string|max:64',
            'branch_id'          => 'required_without:sales|integer',
            'shift_id'           => 'nullable|integer',
            'cashier_id'         => 'required_without:sales|integer',
            'member_id'          => 'nullable|integer',
            'payment_method'     => 'required_without:sales|string|in:cash,debit_card,credit_card,gcash,maya,palawanpay,other',
            'amount_tendered'    => 'required_without:sales|numeric|min:0',
            'items'              => 'required_without:sales|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.product_name' => 'required|string',
            'items.*.sku'        => 'nullable|string',
            'items.*.barcode'    => 'nullable|string',
            'items.*.qty'        => 'required|numeric|min:0.001',
            'items.*.price'      => 'required|numeric|min:0',
            'items.*.discount'   => 'nullable|numeric|min:0',
            'created_at'         => 'nullable|string',
            'sales'              => 'nullable|array',
        ]);

        $branchId = $this->resolveBranchId($request, $data);
        if ($branchId < 1) {
            return response()->json(['success' => false, 'message' => 'Branch required.'], 422);
        }

        if (! empty($data['sales'])) {
            return $this->pushBatch($data['sales'], $branchId);
        }

        return $this->pushSingleSale($data, $branchId);
    }

    /**
     * Branch-scoped delta pull for native SQLite cache.
     */
    public function pull(Request $request): JsonResponse
    {
        $branchId = (int) $request->input('branch_id', $request->user()->branch_id ?? 0);
        if ($branchId < 1) {
            return response()->json(['success' => false, 'message' => 'branch_id required.'], 422);
        }

        $since = $request->input('since');
        $serverTimestamp = now();

        $productQuery = Product::with('category')->where('active', true);
        if ($since) {
            $productQuery->where('updated_at', '>', $since);
        }
        $productModels = $productQuery->get();
        $products = collect($this->inventory->enrichProductsWithStock($productModels, $branchId))->values();

        $categories = Category::query()
            ->when($since, fn ($q) => $q->where('updated_at', '>', $since))
            ->orderBy('name')
            ->get(['id', 'name', 'updated_at']);

        $customers = Customer::query()
            ->when($since, fn ($q) => $q->where('updated_at', '>', $since))
            ->whereNull('deleted_at')
            ->get()
            ->map(fn ($c) => [
                'id'             => $c->id,
                'uuid'           => $c->uuid,
                'card_number'    => $c->card_number,
                'name'           => trim("{$c->first_name} {$c->last_name}"),
                'first_name'     => $c->first_name,
                'last_name'      => $c->last_name,
                'phone'          => $c->phone,
                'email'          => $c->email,
                'loyalty_points' => $c->loyalty_points,
                'status'         => $c->status,
                'updated_at'     => $c->updated_at?->toIso8601String(),
            ]);

        $batchQuery = InventoryBatch::with('product:id,name,sku')
            ->where('branch_id', $branchId)
            ->where('quantity', '>', 0);
        if ($since) {
            $batchQuery->where('updated_at', '>', $since);
        }
        $inventoryBatches = $batchQuery->orderBy('expiry_date')->get()->map(fn ($b) => [
            'id'           => $b->id,
            'product_id'   => $b->product_id,
            'branch_id'    => $b->branch_id,
            'batch_code'   => $b->batch_code,
            'expiry_date'  => $b->expiry_date?->toDateString(),
            'quantity'     => (float) $b->quantity,
            'cost_price'   => $b->cost_price,
            'updated_at'   => $b->updated_at?->toIso8601String(),
        ]);

        $alertQuery = ExpiryAlert::with(['product:id,name,sku'])
            ->where('branch_id', $branchId)
            ->active();
        if ($since) {
            $alertQuery->where('updated_at', '>', $since);
        }
        $expiryAlerts = $alertQuery->orderBy('expiry_date')->get();

        return response()->json([
            'success'           => true,
            'branch_id'         => $branchId,
            'products'          => $products,
            'categories'        => $categories,
            'customers'         => $customers,
            'inventory_batches' => $inventoryBatches,
            'expiry_alerts'     => $expiryAlerts,
            'settings'          => $this->settings->syncMapForBranch($branchId),
            'server_timestamp'  => $serverTimestamp->toIso8601String(),
            'pulled_at'         => $serverTimestamp->toIso8601String(),
        ]);
    }

    public function allCustomers(): JsonResponse
    {
        $customers = Customer::select('id', 'uuid', 'card_number', 'first_name', 'last_name', 'phone', 'email', 'loyalty_points', 'status', 'updated_at')
            ->whereNull('deleted_at')
            ->get()
            ->map(fn ($c) => [
                'id'             => $c->id,
                'uuid'           => $c->uuid,
                'card_number'    => $c->card_number,
                'name'           => "{$c->first_name} {$c->last_name}",
                'first_name'     => $c->first_name,
                'last_name'      => $c->last_name,
                'phone'          => $c->phone,
                'email'          => $c->email,
                'loyalty_points' => $c->loyalty_points,
                'status'         => $c->status,
                'updated_at'     => $c->updated_at?->toIso8601String(),
            ]);

        return response()->json(['customers' => $customers]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function pushSingleSale(array $data, int $branchId): JsonResponse
    {
        $data['branch_id'] = $branchId;

        $existing = PosSale::where('local_id', $data['local_id'])->first();
        if ($existing) {
            return response()->json([
                'success'   => true,
                'duplicate' => true,
                'server_id' => $existing->id,
                'sale'      => $existing->load('items'),
            ]);
        }

        $conflictList = $this->conflicts->detectSaleItemConflicts($branchId, $data['items']);
        if ($conflictList !== []) {
            return response()->json([
                'success'   => false,
                'conflicts' => $conflictList,
                'conflict'  => $conflictList,
                'message'   => 'Sync conflicts detected.',
            ], 409);
        }

        try {
            $sale = $this->saleService->createSale($data);
            $sale->update([
                'local_id'   => $data['local_id'],
                'synced_at'  => now(),
            ]);

            return response()->json([
                'success'   => true,
                'server_id' => $sale->id,
                'sale'      => $sale->load('items'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $sales
     */
    protected function pushBatch(array $sales, int $branchId): JsonResponse
    {
        $results = [];
        $allConflicts = [];

        foreach ($sales as $salePayload) {
            $salePayload['branch_id'] = $branchId;
            $response = $this->pushSingleSale($salePayload, $branchId);
            $body = $response->getData(true);
            $results[] = $body;
            if (! empty($body['conflicts'])) {
                $allConflicts = array_merge($allConflicts, $body['conflicts']);
            }
        }

        if ($allConflicts !== []) {
            return response()->json([
                'success'   => false,
                'conflicts' => $allConflicts,
                'results'   => $results,
            ], 409);
        }

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveBranchId(Request $request, array $data): int
    {
        $branchId = (int) ($data['branch_id'] ?? $request->user()->branch_id ?? 0);
        $fingerprint = trim((string) ($data['device_fingerprint'] ?? $request->input('device_fingerprint', '')));

        if ($fingerprint !== '') {
            $device = Device::where('device_fingerprint', $fingerprint)->first();
            if ($device) {
                return (int) $device->branch_id;
            }
        }

        return $branchId;
    }
}
