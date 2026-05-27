<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Concerns\ResolvesInventoryBranch;
use App\Http\Controllers\Controller;
use App\Models\Inventory\ExpiryAlert;
use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\StockMovement;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryApiController extends Controller
{
    use ResolvesInventoryBranch;

    public function __construct(
        protected InventoryService $inventory,
    ) {}

    public function batches(Request $request): JsonResponse
    {
        $branchId = $this->resolveInventoryBranchId($request);
        $this->authorizeInventoryBranch($branchId);

        $query = InventoryBatch::with('product:id,name,sku')
            ->where('branch_id', $branchId);

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        $batches = $query->orderBy('expiry_date')->orderBy('id')->get();

        return response()->json(['success' => true, 'batches' => $batches]);
    }

    public function expiry(Request $request): JsonResponse
    {
        $branchId = $this->resolveInventoryBranchId($request);
        $this->authorizeInventoryBranch($branchId);

        $alerts = ExpiryAlert::with(['product:id,name,sku', 'batch'])
            ->where('branch_id', $branchId)
            ->active()
            ->orderBy('expiry_date')
            ->get();

        return response()->json(['success' => true, 'alerts' => $alerts]);
    }

    public function adjustments(Request $request): JsonResponse
    {
        $branchId = $this->resolveInventoryBranchId($request);
        $this->authorizeInventoryBranch($branchId);

        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'direction'  => 'required|in:in,out,set',
            'qty'        => 'required|numeric|min:0.001',
            'reason'     => 'required|string|max:500',
        ]);

        $productId = (int) $data['product_id'];
        $userId = (int) auth()->id();

        if ($data['direction'] === 'set') {
            $this->inventory->adjustProduct($branchId, $productId, (float) $data['qty'], $data['reason'], $userId);
        } elseif ($data['direction'] === 'in') {
            $this->inventory->stockIn(
                $branchId,
                [['product_id' => $productId, 'qty' => (float) $data['qty']]],
                'adjustment',
                null,
                'ADJ-API-' . now()->format('YmdHis'),
                $userId,
            );
        } else {
            $this->inventory->stockOut(
                branchId: $branchId,
                productId: $productId,
                qty: (float) $data['qty'],
                type: 'adjustment',
                referenceNumber: 'ADJ-API-' . now()->format('YmdHis'),
                userId: $userId,
                reason: $data['reason'],
            );
        }

        return response()->json([
            'success'       => true,
            'stock_on_hand' => $this->inventory->getStockOnHand($branchId, $productId),
        ]);
    }

    public function movements(Request $request, int $product): JsonResponse
    {
        $branchId = $this->resolveInventoryBranchId($request);
        $this->authorizeInventoryBranch($branchId);

        $movements = StockMovement::where('branch_id', $branchId)
            ->where('product_id', $product)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json(['success' => true, 'movements' => $movements]);
    }
}
