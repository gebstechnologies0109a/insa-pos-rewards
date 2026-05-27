<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Concerns\ResolvesInventoryBranch;
use App\Http\Controllers\Controller;
use App\Models\POS\Branch;
use App\Models\POS\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;

class InventoryAdjustmentController extends Controller
{
    use ResolvesInventoryBranch;

    public function __construct(
        protected InventoryService $inventory,
    ) {}

    public function create(Request $request)
    {
        $branchId = $this->resolveInventoryBranchId($request);
        $this->authorizeInventoryBranch($branchId);

        $products = Product::where('active', true)->orderBy('name')->get(['id', 'name', 'sku']);
        $branches = auth()->user()->isBranchScoped()
            ? collect()
            : Branch::orderBy('name')->get();

        $productId = $request->input('product_id');
        $currentStock = $productId
            ? $this->inventory->getStockOnHand($branchId, (int) $productId)
            : null;

        return view('backoffice.inventory.adjustment', compact(
            'products',
            'branches',
            'branchId',
            'productId',
            'currentStock',
        ));
    }

    public function store(Request $request)
    {
        $branchId = $this->resolveInventoryBranchId($request);
        $this->authorizeInventoryBranch($branchId);

        $data = $request->validate([
            'product_id'   => 'required|exists:products,id',
            'direction'    => 'required|in:in,out,set',
            'qty'          => 'required|numeric|min:0.001',
            'reason'       => 'required|string|max:500',
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
                'ADJ-' . now()->format('YmdHis'),
                $userId,
            );
        } else {
            $this->inventory->stockOut(
                branchId: $branchId,
                productId: $productId,
                qty: (float) $data['qty'],
                type: 'adjustment',
                referenceNumber: 'ADJ-' . now()->format('YmdHis'),
                userId: $userId,
                reason: $data['reason'],
            );
        }

        return redirect()
            ->route('backoffice.inventory.adjustment', ['branch_id' => $branchId, 'product_id' => $productId])
            ->with('success', 'Adjustment recorded. New stock: ' . $this->inventory->getStockOnHand($branchId, $productId));
    }
}
