<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\AuditLog;
use App\Models\EPayPlus\Product;
use App\Models\EPayPlus\Provider;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EPayProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('provider');

        if ($request->provider_id) {
            $query->where('provider_id', $request->provider_id);
        }
        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%");
            });
        }
        if ($request->status === 'active') {
            $query->where('is_active', true);
        } elseif ($request->status === 'inactive') {
            $query->where('is_active', false);
        }

        $products  = $query->orderBy('provider_id')->orderBy('sort_order')->orderBy('name')->paginate(50)->withQueryString();
        $providers = Provider::orderBy('name')->get();

        return view('epayplus.products', compact('products', 'providers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'provider_id'    => 'required|exists:epay_providers,id',
            'type'           => 'required|in:ELOAD,BILLS,ECASH,WIFI,OTHER',
            'code'           => 'required|string|max:50|unique:epay_products,code',
            'name'           => 'required|string|max:255',
            'amount'         => 'required|numeric|min:0',
            'retailer_price' => 'nullable|numeric|min:0',
            'fee'            => 'nullable|numeric|min:0',
            'commission'     => 'nullable|numeric|min:0',
            'description'    => 'nullable|string|max:500',
            'keyword'        => 'nullable|string|max:100',
            'sms_format'     => 'nullable|string|max:500',
            'validity_days'  => 'nullable|integer|min:0',
            'sort_order'     => 'nullable|integer|min:0',
            'is_active'      => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $product = Product::create($data);

        AuditLog::record(auth()->id(), 'product_created', $product, "Created product: {$product->name}");

        return back()->with('success', "Product '{$product->name}' created.");
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'provider_id'    => 'required|exists:epay_providers,id',
            'type'           => 'required|in:ELOAD,BILLS,ECASH,WIFI,OTHER',
            'code'           => 'required|string|max:50|unique:epay_products,code,' . $product->id,
            'name'           => 'required|string|max:255',
            'amount'         => 'required|numeric|min:0',
            'retailer_price' => 'nullable|numeric|min:0',
            'fee'            => 'nullable|numeric|min:0',
            'commission'     => 'nullable|numeric|min:0',
            'description'    => 'nullable|string|max:500',
            'keyword'        => 'nullable|string|max:100',
            'sms_format'     => 'nullable|string|max:500',
            'validity_days'  => 'nullable|integer|min:0',
            'sort_order'     => 'nullable|integer|min:0',
            'is_active'      => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $product->update($data);

        AuditLog::record(auth()->id(), 'product_updated', $product, "Updated product: {$product->name}");

        return back()->with('success', "Product '{$product->name}' updated.");
    }

    public function toggleStatus(Product $product)
    {
        $product->update(['is_active' => !$product->is_active]);
        $status = $product->is_active ? 'enabled' : 'disabled';

        AuditLog::record(auth()->id(), "product_{$status}", $product, "{$product->name} {$status}");

        return back()->with('success', "Product '{$product->name}' {$status}.");
    }

    public function export()
    {
        $products = Product::with('provider')->orderBy('provider_id')->orderBy('name')->get();

        return new StreamedResponse(function () use ($products) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Provider', 'Type', 'Code', 'Name', 'Amount', 'Retailer Price', 'Fee', 'Commission', 'Active', 'Keyword']);

            foreach ($products as $p) {
                fputcsv($handle, [
                    $p->id, $p->provider?->name, $p->type, $p->code, $p->name,
                    $p->amount, $p->retailer_price, $p->fee, $p->commission,
                    $p->is_active ? 'Yes' : 'No', $p->keyword,
                ]);
            }
            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="epayplus-products-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $file    = $request->file('file');
        $handle  = fopen($file->getRealPath(), 'r');
        $header  = fgetcsv($handle);
        $created = 0;
        $updated = 0;
        $errors  = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 6) {
                $errors++;
                continue;
            }

            try {
                $provider = Provider::where('name', $row[1])->first();
                if (!$provider) {
                    $errors++;
                    continue;
                }

                $product = Product::updateOrCreate(
                    ['code' => $row[3]],
                    [
                        'provider_id'    => $provider->id,
                        'type'           => $row[2] ?? 'ELOAD',
                        'name'           => $row[4],
                        'amount'         => (float) ($row[5] ?? 0),
                        'retailer_price' => (float) ($row[6] ?? 0),
                        'fee'            => (float) ($row[7] ?? 0),
                        'commission'     => (float) ($row[8] ?? 0),
                        'is_active'      => strtolower($row[9] ?? 'yes') === 'yes',
                        'keyword'        => $row[10] ?? null,
                    ]
                );

                $product->wasRecentlyCreated ? $created++ : $updated++;
            } catch (\Exception $e) {
                $errors++;
            }
        }
        fclose($handle);

        AuditLog::record(auth()->id(), 'products_imported', null, "Imported products: {$created} created, {$updated} updated, {$errors} errors");

        return back()->with('success', "Import complete: {$created} created, {$updated} updated, {$errors} errors.");
    }
}
