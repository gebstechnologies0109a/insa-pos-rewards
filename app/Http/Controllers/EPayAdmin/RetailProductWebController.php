<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Concerns\ResolvesEpayRetailer;
use App\Http\Controllers\Controller;
use App\Models\EPayPlus\AuditLog;
use App\Models\EPayPlus\RetailProduct;
use App\Models\EPayPlus\Retailer;
use Illuminate\Http\Request;

class RetailProductWebController extends Controller
{
    use ResolvesEpayRetailer;

    public function index(Request $request)
    {
        $retailerId = $this->resolveWebRetailerId($request);
        $retailers = Retailer::where('is_active', true)->orderBy('business_name')->get(['id', 'business_name', 'account_id']);

        $query = RetailProduct::with('retailer')->forRetailer($retailerId);

        if ($request->search) {
            $q = $request->search;
            $query->where(function ($b) use ($q) {
                $b->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%");
            });
        }
        if ($request->category) {
            $query->where('category', $request->category);
        }

        $products = $query->orderBy('sort_order')->orderBy('name')->paginate(30)->withQueryString();
        $categories = RetailProduct::forRetailer($retailerId)->whereNotNull('category')->distinct()->pluck('category');

        return view('epayplus.retail-products.index', compact('products', 'retailers', 'retailerId', 'categories'));
    }

    public function store(Request $request)
    {
        $retailerId = $this->resolveWebRetailerId($request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'sku' => 'nullable|string|max:64',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data['retailer_id'] = $retailerId;
        $data['is_active'] = $request->boolean('is_active', true);
        $product = RetailProduct::create($data);

        AuditLog::record(auth()->id(), 'retail_product_created', $product, "Created retail product: {$product->name}");

        return back()->with('success', "Product '{$product->name}' added.");
    }

    public function update(Request $request, RetailProduct $retailProduct)
    {
        $retailerId = $this->resolveWebRetailerId($request);
        if ((int) $retailProduct->retailer_id !== $retailerId) {
            abort(404);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'sku' => 'nullable|string|max:64',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $retailProduct->update($data);

        return back()->with('success', "Product '{$retailProduct->name}' updated.");
    }

    public function destroy(Request $request, RetailProduct $retailProduct)
    {
        $retailerId = $this->resolveWebRetailerId($request);
        if ((int) $retailProduct->retailer_id !== $retailerId) {
            abort(404);
        }

        $name = $retailProduct->name;
        $retailProduct->delete();

        return back()->with('success', "Product '{$name}' removed.");
    }
}
