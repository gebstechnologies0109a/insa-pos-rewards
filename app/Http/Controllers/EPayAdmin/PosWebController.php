<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Concerns\ResolvesEpayRetailer;
use App\Http\Controllers\Controller;
use App\Models\EPayPlus\PosSale;
use App\Models\EPayPlus\RetailProduct;
use App\Models\EPayPlus\Retailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosWebController extends Controller
{
    use ResolvesEpayRetailer;

    public function index(Request $request)
    {
        $retailerId = $this->resolveWebRetailerId($request);
        $retailers = Retailer::where('is_active', true)->orderBy('business_name')->get(['id', 'business_name', 'account_id']);
        $retailer = Retailer::find($retailerId);

        $retailProducts = RetailProduct::forRetailer($retailerId)
            ->active()
            ->where('stock', '>', 0)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('epayplus.pos.index', compact('retailers', 'retailerId', 'retailer', 'retailProducts'));
    }

    public function checkout(Request $request): JsonResponse
    {
        $retailerId = $this->resolveWebRetailerId($request);
        if ($retailerId <= 0) {
            return response()->json(['success' => false, 'message' => 'Select a retailer first.'], 422);
        }

        $data = $request->validate([
            'payment_method' => 'nullable|string|max:50',
            'lines' => 'required|array|min:1',
            'lines.*.product_type' => 'required|in:retail',
            'lines.*.product_id' => 'required|integer',
            'lines.*.product_name' => 'required|string|max:255',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            $sale = DB::transaction(function () use ($retailerId, $data, $request) {
                $subtotal = 0.0;
                $lineRows = [];

                foreach ($data['lines'] as $line) {
                    $qty = (int) $line['quantity'];
                    $unit = (float) $line['unit_price'];
                    $lineTotal = round($qty * $unit, 2);
                    $subtotal += $lineTotal;

                    $product = RetailProduct::forRetailer($retailerId)
                        ->active()
                        ->lockForUpdate()
                        ->findOrFail($line['product_id']);

                    if ($product->stock < $qty) {
                        throw new \RuntimeException("Insufficient stock for {$product->name}.");
                    }
                    $product->decrement('stock', $qty);

                    $lineRows[] = [
                        'product_type' => 'retail',
                        'product_id' => $product->id,
                        'product_name' => $line['product_name'],
                        'sku' => $product->sku,
                        'quantity' => $qty,
                        'unit_price' => $unit,
                        'line_total' => $lineTotal,
                    ];
                }

                $sale = PosSale::create([
                    'retailer_id' => $retailerId,
                    'reference' => 'POS-'.strtoupper(Str::random(10)),
                    'subtotal' => $subtotal,
                    'total' => $subtotal,
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'source' => 'web',
                    'notes' => null,
                ]);

                foreach ($lineRows as $row) {
                    $sale->lines()->create($row);
                }

                return $sale;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'reference' => $sale->reference,
            'total' => (float) $sale->total,
        ]);
    }
}
