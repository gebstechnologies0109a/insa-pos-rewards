<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Product;
use App\Models\EPayPlus\ProductPricing;
use App\Models\EPayPlus\Retailer;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductPricing::with(['product', 'retailer'])->orderByDesc('created_at');

        if ($request->filled('retailer_id')) {
            $query->where('retailer_id', $request->retailer_id);
        }

        $pricing = $query->paginate(30)->withQueryString();
        $products = Product::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'amount']);
        $retailers = Retailer::orderBy('business_name')->get(['id', 'business_name']);

        return view('epayplus.pricing.index', compact('pricing', 'products', 'retailers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'nullable|integer|exists:epay_products,id',
            'product_code' => 'nullable|string|max:50',
            'retailer_id' => 'nullable|integer|exists:epay_retailers,id',
            'discount_type' => 'required|in:percentage,fixed,override',
            'discount_value' => 'required|numeric|min:0',
            'custom_price' => 'nullable|numeric|min:0',
        ]);

        ProductPricing::create($validated);

        return back()->with('success', 'Pricing rule created.');
    }

    public function update(Request $request, ProductPricing $pricing)
    {
        $validated = $request->validate([
            'discount_type' => 'sometimes|in:percentage,fixed,override',
            'discount_value' => 'sometimes|numeric|min:0',
            'custom_price' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $pricing->update($validated);

        return back()->with('success', 'Pricing rule updated.');
    }

    public function destroy(ProductPricing $pricing)
    {
        $pricing->delete();
        return back()->with('success', 'Pricing rule deleted.');
    }
}
