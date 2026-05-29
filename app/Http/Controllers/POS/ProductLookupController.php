<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\POS\Category;
use App\Models\POS\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductLookupController extends Controller
{
    public function __construct(
        protected InventoryService $inventory,
    ) {}

    public function all(Request $request): JsonResponse|Response
    {
        $branchId = (int) $request->input('branch_id', auth()->user()?->branch_id ?? 1);
        $categoryId = $request->input('category_id');

        $catalogVersion = $this->catalogVersion($branchId);
        $etag = '"' . $catalogVersion . '"';

        if ($request->header('If-None-Match') === $etag) {
            return response('', Response::HTTP_NOT_MODIFIED)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'private, max-age=60');
        }

        $query = Product::where('active', true);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->orderBy('products.name')->get();
        $enriched = $this->inventory->enrichProductsWithStock($products, $branchId);

        $categories = Category::select('id', 'name')->orderBy('name')->get();

        return response()->json([
            'products'         => $enriched,
            'categories'       => $categories,
            'catalog_version'  => $catalogVersion,
        ])->header('ETag', $etag)
          ->header('Cache-Control', 'private, max-age=60');
    }

    /** Stable fingerprint for POS catalog cache invalidation (ETag / If-None-Match). */
    private function catalogVersion(int $branchId): string
    {
        $productMax = Product::where('active', true)->max('updated_at');
        $categoryMax = Category::max('updated_at');
        $productCount = Product::where('active', true)->count();
        $categoryCount = Category::count();

        return md5("{$branchId}|{$productMax}|{$categoryMax}|{$productCount}|{$categoryCount}");
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        $branchId = (int) $request->input('branch_id', auth()->user()?->branch_id ?? 1);

        if (strlen($query) < 1) {
            return response()->json([]);
        }

        $exact = Product::where('active', true)
            ->where(function ($q) use ($query) {
                $q->where('barcode', $query)
                  ->orWhere('sku', $query);
            })
            ->first();

        if ($exact) {
            $enriched = $this->inventory->enrichProductsWithStock([$exact], $branchId);

            return response()->json($enriched);
        }

        $products = Product::where('active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%")
                    ->orWhere('barcode', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($this->inventory->enrichProductsWithStock($products, $branchId));
    }
}
