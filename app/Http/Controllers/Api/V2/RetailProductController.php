<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Concerns\ResolvesEpayRetailer;
use App\Http\Controllers\Controller;
use App\Models\EPayPlus\RetailProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RetailProductController extends Controller
{
    use ResolvesEpayRetailer;

    public function index(Request $request): JsonResponse
    {
        $retailer = $this->retailerFromApi($request);

        $products = RetailProduct::forRetailer($retailer->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => $p->toApiArray());

        return response()->json(['success' => true, 'products' => $products]);
    }

    public function store(Request $request): JsonResponse
    {
        $retailer = $this->retailerFromApi($request);

        $data = $this->validatedProduct($request);
        $product = RetailProduct::create(array_merge($data, ['retailer_id' => $retailer->id]));

        return response()->json(['success' => true, 'product' => $product->toApiArray()], 201);
    }

    public function update(Request $request, RetailProduct $retailProduct): JsonResponse
    {
        $retailer = $this->retailerFromApi($request);
        $this->assertOwnership($retailProduct, $retailer->id);

        $retailProduct->update($this->validatedProduct($request));

        return response()->json(['success' => true, 'product' => $retailProduct->fresh()->toApiArray()]);
    }

    public function destroy(Request $request, RetailProduct $retailProduct): JsonResponse
    {
        $retailer = $this->retailerFromApi($request);
        $this->assertOwnership($retailProduct, $retailer->id);

        $retailProduct->delete();

        return response()->json(['success' => true, 'message' => 'Product deleted.']);
    }

    private function validatedProduct(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'sku' => 'nullable|string|max:64',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sku' => $data['sku'] ?? null,
            'price' => $data['price'],
            'stock' => $data['stock'],
            'category' => $data['category'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    private function assertOwnership(RetailProduct $product, int $retailerId): void
    {
        if ((int) $product->retailer_id !== $retailerId) {
            abort(404);
        }
    }
}
