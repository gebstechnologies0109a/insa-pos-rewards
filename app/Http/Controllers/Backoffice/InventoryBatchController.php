<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Concerns\ResolvesInventoryBranch;
use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryBatch;
use App\Models\POS\Branch;
use App\Models\POS\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;

class InventoryBatchController extends Controller
{
    use ResolvesInventoryBranch;

    public function __construct(
        protected InventoryService $inventory,
    ) {}

    public function index(Request $request)
    {
        $branchId = $this->resolveInventoryBranchId($request);
        $this->authorizeInventoryBranch($branchId);

        $query = InventoryBatch::with(['product', 'branch'])
            ->where('branch_id', $branchId);

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($supplier = $request->input('supplier')) {
            $query->where('supplier_name', 'like', '%' . $supplier . '%');
        }

        if ($expiry = $request->input('expiry_filter')) {
            if ($expiry === 'expired') {
                $query->whereNotNull('expiry_date')->where('expiry_date', '<', now()->toDateString());
            } elseif ($expiry === '7d') {
                $query->whereNotNull('expiry_date')
                    ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(7)->toDateString()]);
            } elseif ($expiry === '30d') {
                $query->whereNotNull('expiry_date')
                    ->whereBetween('expiry_date', [now()->addDays(8)->toDateString(), now()->addDays(30)->toDateString()]);
            } elseif ($expiry === 'none') {
                $query->whereNull('expiry_date');
            }
        }

        $batches = $query->orderByDesc('received_at')->paginate(50)->withQueryString();
        $branches = auth()->user()->isSuperAdmin() || ! auth()->user()->isBranchScoped()
            ? Branch::orderBy('name')->get()
            : collect();
        $products = Product::where('active', true)->orderBy('name')->get(['id', 'name', 'sku']);

        return view('backoffice.inventory.batches', compact('batches', 'branches', 'branchId', 'products'));
    }

    public function edit(InventoryBatch $batch)
    {
        $this->authorizeInventoryBranch((int) $batch->branch_id);
        $batch->load('product');

        return view('backoffice.inventory.batch-edit', compact('batch'));
    }

    public function update(Request $request, InventoryBatch $batch)
    {
        $this->authorizeInventoryBranch((int) $batch->branch_id);

        $data = $request->validate([
            'batch_code'    => 'nullable|string|max:100',
            'expiry_date'   => 'nullable|date',
            'cost_price'    => 'nullable|numeric|min:0',
            'supplier_name' => 'nullable|string|max:255',
        ]);

        $batch->update($data);

        return redirect()
            ->route('backoffice.inventory.batches', ['branch_id' => $batch->branch_id])
            ->with('success', 'Batch updated.');
    }

    public function adjust(Request $request, InventoryBatch $batch)
    {
        $this->authorizeInventoryBranch((int) $batch->branch_id);

        $data = $request->validate([
            'quantity' => 'required|numeric|min:0',
            'reason'   => 'required|string|max:500',
        ]);

        $this->inventory->adjustBatch(
            $batch->id,
            (float) $data['quantity'],
            $data['reason'],
            (int) auth()->id(),
        );

        return redirect()
            ->route('backoffice.inventory.batches', ['branch_id' => $batch->branch_id])
            ->with('success', 'Batch quantity adjusted.');
    }
}
