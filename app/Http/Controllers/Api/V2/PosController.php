<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Concerns\ResolvesEpayRetailer;
use App\Http\Controllers\Controller;
use App\Models\EPayPlus\PosSale;
use App\Models\EPayPlus\RetailProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosController extends Controller
{
    use ResolvesEpayRetailer;

    public function catalog(Request $request): JsonResponse
    {
        $retailer = $this->retailerFromApi($request);

        $retailProducts = RetailProduct::forRetailer($retailer->id)
            ->active()
            ->where('stock', '>', 0)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => $p->toApiArray());

        return response()->json([
            'success' => true,
            'services' => [
                ['key' => 'eload', 'label' => 'E-Load', 'route' => 'eload'],
                ['key' => 'bills', 'label' => 'Bills Payment', 'route' => 'bills'],
                ['key' => 'ecash', 'label' => 'Cash-in', 'route' => 'ecash'],
                ['key' => 'rfid', 'label' => 'RFID', 'route' => 'rfid'],
            ],
            'retailProducts' => $retailProducts,
        ]);
    }

    public function recordSale(Request $request): JsonResponse
    {
        $retailer = $this->retailerFromApi($request);

        $data = $request->validate([
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
            'source' => 'nullable|string|max:20',
            'lines' => 'required|array|min:1',
            'lines.*.product_type' => 'required|in:retail,eload,bills,ecash,rfid',
            'lines.*.product_id' => 'nullable|integer',
            'lines.*.product_name' => 'required|string|max:255',
            'lines.*.sku' => 'nullable|string|max:64',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.unit_price' => 'required|numeric|min:0',
        ]);

        $hasRetail = collect($data['lines'])->contains(fn ($l) => $l['product_type'] === 'retail');
        if (!$hasRetail) {
            return response()->json([
                'success' => false,
                'message' => 'POS checkout requires at least one retail line item.',
            ], 422);
        }

        try {
            $sale = DB::transaction(function () use ($retailer, $data, $request) {
                $subtotal = 0.0;
                $lineRows = [];

                foreach ($data['lines'] as $line) {
                    $qty = (int) $line['quantity'];
                    $unit = (float) $line['unit_price'];
                    $lineTotal = round($qty * $unit, 2);
                    $subtotal += $lineTotal;

                    if ($line['product_type'] === 'retail') {
                        $product = RetailProduct::forRetailer($retailer->id)
                            ->active()
                            ->lockForUpdate()
                            ->find($line['product_id'] ?? 0);

                        if (!$product) {
                            throw new \RuntimeException('Retail product not found.');
                        }
                        if ($product->stock < $qty) {
                            throw new \RuntimeException("Insufficient stock for {$product->name}.");
                        }
                        $product->decrement('stock', $qty);
                    }

                    $lineRows[] = [
                        'product_type' => $line['product_type'],
                        'product_id' => $line['product_id'] ?? null,
                        'product_name' => $line['product_name'],
                        'sku' => $line['sku'] ?? null,
                        'quantity' => $qty,
                        'unit_price' => $unit,
                        'line_total' => $lineTotal,
                    ];
                }

                $sale = PosSale::create([
                    'retailer_id' => $retailer->id,
                    'reference' => 'POS-'.strtoupper(Str::random(10)),
                    'subtotal' => $subtotal,
                    'total' => $subtotal,
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'source' => $data['source'] ?? 'android',
                    'notes' => $data['notes'] ?? null,
                ]);

                foreach ($lineRows as $row) {
                    $sale->lines()->create($row);
                }

                return $sale->load('lines');
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'sale' => [
                'id' => $sale->id,
                'reference' => $sale->reference,
                'subtotal' => (float) $sale->subtotal,
                'total' => (float) $sale->total,
                'paymentMethod' => $sale->payment_method,
                'createdAt' => $sale->created_at->toISOString(),
                'lines' => $sale->lines->map(fn ($l) => [
                    'productType' => $l->product_type,
                    'productName' => $l->product_name,
                    'quantity' => $l->quantity,
                    'unitPrice' => (float) $l->unit_price,
                    'lineTotal' => (float) $l->line_total,
                ]),
            ],
        ]);
    }
}
