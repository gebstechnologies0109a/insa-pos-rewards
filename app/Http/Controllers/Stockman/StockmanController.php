<?php

namespace App\Http\Controllers\Stockman;

use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryBatch;
use App\Models\POS\Branch;
use App\Models\POS\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;
class StockmanController extends Controller
{
    public function __construct(
        protected InventoryService $inventory,
    ) {}

    public function inventory(Request $request)
    {
        $user = auth()->user();
        $branchId = $user->isBranchScoped()
            ? $user->branch_id
            : ($request->input('branch_id', $user->branch_id ?? 1));

        $query = Product::select('products.id', 'products.name', 'products.sku', 'products.barcode', 'products.category_id')
            ->where('products.active', true);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                  ->orWhere('products.sku', 'like', "%{$search}%")
                  ->orWhere('products.barcode', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('products.name')->paginate(50)->withQueryString();
        $ids = $products->pluck('id')->all();
        $stockMap = $this->inventory->stockTotalsForProducts((int) $branchId, $ids);

        $products->getCollection()->transform(function ($p) use ($stockMap) {
            $p->stock_on_hand = $stockMap[$p->id] ?? 0;

            return $p;
        });

        $branches = $user->isBranchScoped() ? collect() : Branch::orderBy('name')->get();

        return view('stockman.inventory', compact('products', 'branches', 'branchId'));
    }

    public function audit(Request $request)
    {
        $user = auth()->user();
        $branchId = (int) ($user->isBranchScoped()
            ? $user->branch_id
            : ($request->input('branch_id', $user->branch_id ?? 1)));

        $product = null;
        $batches = collect();
        $search = $request->input('search');

        if ($search) {
            $product = Product::where('active', true)
                ->where(function ($q) use ($search) {
                    $q->where('barcode', $search)
                        ->orWhere('sku', $search)
                        ->orWhere('name', 'like', "%{$search}%");
                })
                ->first();

            if ($product) {
                $batches = InventoryBatch::forBranch($branchId)
                    ->forProduct($product->id)
                    ->orderBy('expiry_date')
                    ->orderBy('id')
                    ->get();
            }
        }

        $branches = $user->isBranchScoped() ? collect() : Branch::orderBy('name')->get();

        return view('stockman.audit', compact('product', 'batches', 'branches', 'branchId', 'search'));
    }

    public function auditUpdate(Request $request)
    {
        $user = auth()->user();
        $branchId = (int) ($user->isBranchScoped()
            ? $user->branch_id
            : ($request->input('branch_id', $user->branch_id ?? 1)));

        $data = $request->validate([
            'batch_id' => 'required|exists:inventory_batches,id',
            'quantity' => 'required|numeric|min:0',
            'reason'   => 'required|string|max:500',
        ]);

        $batch = InventoryBatch::findOrFail($data['batch_id']);

        if ((int) $batch->branch_id !== $branchId) {
            abort(403, 'Batch does not belong to this branch.');
        }

        $this->inventory->adjustBatch(
            $batch->id,
            (float) $data['quantity'],
            'Stock audit: ' . $data['reason'],
            (int) $user->id,
        );

        return redirect()
            ->route('stockman.audit', ['search' => $request->input('search_q'), 'branch_id' => $branchId])
            ->with('success', 'Counted quantity saved.');
    }

    public function stockInForm()
    {
        $user = auth()->user();
        $products = Product::where('active', true)->orderBy('name')->get();
        $branches = $user->isBranchScoped() ? collect() : Branch::orderBy('name')->get();
        $defaultBranchId = $user->branch_id ?? ($branches->first()?->id ?? 1);

        return view('stockman.stock-in', compact('products', 'branches', 'defaultBranchId'));
    }

    public function stockInStore(Request $request)
    {
        $user = auth()->user();

        $rules = [
            'supplier_name'        => 'nullable|string|max:255',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.qty'          => 'required|integer|min:1',
            'items.*.cost'         => 'required|numeric|min:0',
            'items.*.expiry_date'  => 'nullable|date',
        ];

        if (! $user->isBranchScoped()) {
            $rules['branch_id'] = 'required|exists:branches,id';
        }

        $data = $request->validate($rules);

        $branchId = $user->isBranchScoped()
            ? $user->branch_id
            : $data['branch_id'];

        $result = app(\App\Services\POS\StockInService::class)->create([
            'branch_id'     => $branchId,
            'user_id'       => $user->id,
            'supplier_name' => $data['supplier_name'] ?? null,
            'items'         => collect($data['items'])->map(function ($item) {
                $product = Product::find($item['product_id']);

                return [
                    'product_id'   => $item['product_id'],
                    'product_name' => $product->name,
                    'sku'          => $product->sku,
                    'qty'          => $item['qty'],
                    'cost'         => $item['cost'],
                    'expiry_date'  => $item['expiry_date'] ?? null,
                ];
            })->toArray(),
        ]);

        return redirect()->route('stockman.inventory')
            ->with('success', "Stock-in #{$result->stock_in_number} recorded successfully.");
    }
}
