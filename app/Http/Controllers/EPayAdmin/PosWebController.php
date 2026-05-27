<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Api\V2\TransactionController;
use App\Http\Controllers\Concerns\ResolvesEpayRetailer;
use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Retailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosWebController extends Controller
{
    use ResolvesEpayRetailer;

    public function index(Request $request)
    {
        // Native ePay service POS (Load/Bills/etc.) — not INSA retail cashier.
        if ($request->boolean('epay')) {
            $retailerId = $this->resolveWebRetailerId($request);
            $retailers = Retailer::where('is_active', true)->orderBy('business_name')->get(['id', 'business_name', 'account_id']);
            $retailer = Retailer::find($retailerId);

            return view('epayplus.pos.index', compact('retailers', 'retailerId', 'retailer'));
        }

        // Default: embed INSA cashier on insapos host (never epayplus /pos/* — product middleware blocks it).
        return view('epayplus.pos.insa-embed', [
            'insaUrl' => (string) config('product.insa_pos_cashier_url'),
        ]);
    }

    public function checkout(Request $request, TransactionController $transactions): JsonResponse
    {
        $retailer = $this->webRetailer($request);
        if (!$retailer) {
            return response()->json(['success' => false, 'message' => 'No retailer selected'], 404);
        }

        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|in:ELOAD,BILLS,ECASH,RFID',
            'items.*.provider_code' => 'required|string',
            'items.*.product_code' => 'nullable|string',
            'items.*.amount' => 'required|numeric|min:0.01',
            'items.*.target' => 'nullable|string',
        ]);

        $results = [];
        $errors = [];

        foreach ($data['items'] as $index => $item) {
            $sub = Request::create('/api/v2/transactions', 'POST');
            $sub->attributes->set('retailer', $retailer);
            $sub->headers->set('X-Device-Id', 'web-pos');

            try {
                $response = match ($item['type']) {
                    'ELOAD' => $transactions->processEload($this->withEloadFields($sub, $item)),
                    'BILLS' => $transactions->processBillPayment($this->withBillFields($sub, $item)),
                    'ECASH' => $transactions->processEcash($this->withEcashFields($sub, $item)),
                    'RFID' => $transactions->processRfid($this->withRfidFields($sub, $item)),
                };
                $body = $response->getData(true);
                if ($response->getStatusCode() >= 400 || !($body['success'] ?? false)) {
                    $errors[] = [
                        'index' => $index,
                        'message' => $body['message'] ?? 'Transaction failed',
                    ];
                } else {
                    $results[] = [
                        'index' => $index,
                        'referenceNumber' => $body['referenceNumber'] ?? null,
                        'status' => $body['status'] ?? 'SUCCESS',
                        'message' => $body['message'] ?? 'OK',
                    ];
                }
            } catch (\Throwable $e) {
                $errors[] = ['index' => $index, 'message' => $e->getMessage()];
            }
        }

        if ($errors && !$results) {
            return response()->json([
                'success' => false,
                'message' => $errors[0]['message'],
                'errors' => $errors,
            ], 400);
        }

        return response()->json([
            'success' => count($errors) === 0,
            'message' => count($errors) === 0
                ? count($results) . ' transaction(s) completed.'
                : count($results) . ' succeeded, ' . count($errors) . ' failed.',
            'results' => $results,
            'errors' => $errors,
            'balances' => $retailer->fresh()->walletBalances(),
        ], count($errors) ? 207 : 200);
    }

    private function withEloadFields(Request $request, array $item): Request
    {
        $request->merge([
            'provider_code' => $item['provider_code'],
            'product_code' => $item['product_code'] ?? $item['provider_code'],
            'mobile_number' => $item['target'] ?? '',
            'amount' => $item['amount'],
        ]);

        return $request;
    }

    private function withBillFields(Request $request, array $item): Request
    {
        $request->merge([
            'biller_code' => $item['provider_code'],
            'product_code' => $item['product_code'] ?? null,
            'account_number' => $item['target'] ?? '',
            'amount' => $item['amount'],
        ]);

        return $request;
    }

    private function withEcashFields(Request $request, array $item): Request
    {
        $request->merge([
            'provider_code' => $item['provider_code'],
            'mobile_number' => $item['target'] ?? '',
            'amount' => $item['amount'],
        ]);

        return $request;
    }

    private function withRfidFields(Request $request, array $item): Request
    {
        $request->merge([
            'provider_code' => $item['provider_code'],
            'account_number' => $item['target'] ?? '',
            'amount' => $item['amount'],
        ]);

        return $request;
    }
}
