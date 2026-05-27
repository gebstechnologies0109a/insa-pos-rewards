<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Product;
use App\Models\EPayPlus\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function processEload(Request $request): JsonResponse
    {
        $request->validate([
            'provider_code' => 'required|string',
            'product_code' => 'required|string',
            'mobile_number' => 'required|string|min:10|max:13',
            'amount' => 'required|numeric|min:1',
            'reference_id' => 'nullable|string',
        ]);

        $retailer = $request->attributes->get('retailer');
        $product = Product::where('code', $request->product_code)->active()->first();

        $cost = $product ? $product->retailer_price : $request->amount;
        $commission = $product ? $product->commission : 0;

        if (!$retailer->hasSufficientBalance($cost, 'eload')) {
            $wallets = $retailer->walletBalances();
            return response()->json([
                'success' => false,
                'message' => 'Insufficient E-Load wallet balance. Current: ₱' . number_format($wallets['eload'], 2),
            ], 400);
        }

        return DB::transaction(function () use ($retailer, $product, $request, $cost, $commission) {
            $balanceBefore = $retailer->walletBalances()['eload'];
            $retailer->deductBalance($cost, 'eload');

            $transaction = Transaction::create([
                'retailer_id' => $retailer->id,
                'product_id' => $product?->id,
                'type' => 'ELOAD',
                'reference_number' => $this->generateRefNumber(),
                'provider_code' => $request->provider_code,
                'product_code' => $request->product_code,
                'product_name' => $product?->name ?? 'E-Load',
                'target_number' => $request->mobile_number,
                'amount' => $request->amount,
                'fee' => $product?->fee ?? 0,
                'commission' => $commission,
                'retailer_cost' => $cost,
                'status' => 'SUCCESS',
                'payment_method' => 'WALLET',
                'balance_before' => $balanceBefore,
                'balance_after' => $retailer->fresh()->walletBalances()['eload'],
                'device_id' => $request->header('X-Device-Id'),
                'ip_address' => $request->ip(),
                'completed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'referenceNumber' => $transaction->reference_number,
                'status' => 'SUCCESS',
                'message' => 'E-Load sent successfully.',
                'balance' => $retailer->fresh()->walletBalances()['combined'],
                'eloadBalance' => $retailer->fresh()->walletBalances()['eload'],
                'billsBalance' => $retailer->fresh()->walletBalances()['bills'],
            ]);
        });
    }

    public function processBillPayment(Request $request): JsonResponse
    {
        $request->validate([
            'biller_code' => 'required|string',
            'product_code' => 'nullable|string',
            'account_number' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'reference_id' => 'nullable|string',
        ]);

        $retailer = $request->attributes->get('retailer');
        $product = Product::where('code', $request->product_code)->first();
        $fee = $product?->fee ?? 0;
        $totalCost = $request->amount + $fee;

        if (!$retailer->hasSufficientBalance($totalCost, 'bills')) {
            $wallets = $retailer->walletBalances();
            return response()->json([
                'success' => false,
                'message' => 'Insufficient Bills/Cash-In wallet balance. Current: ₱' . number_format($wallets['bills'], 2),
            ], 400);
        }

        return DB::transaction(function () use ($retailer, $product, $request, $totalCost, $fee) {
            $balanceBefore = $retailer->walletBalances()['bills'];
            $retailer->deductBalance($totalCost, 'bills');

            $transaction = Transaction::create([
                'retailer_id' => $retailer->id,
                'product_id' => $product?->id,
                'type' => 'BILLS',
                'reference_number' => $this->generateRefNumber(),
                'provider_code' => $request->biller_code,
                'product_code' => $request->product_code,
                'product_name' => $product?->name ?? 'Bill Payment',
                'target_number' => $request->account_number,
                'amount' => $request->amount,
                'fee' => $fee,
                'commission' => $product?->commission ?? 0,
                'retailer_cost' => $totalCost,
                'status' => 'PROCESSING',
                'payment_method' => 'WALLET',
                'balance_before' => $balanceBefore,
                'balance_after' => $retailer->fresh()->walletBalances()['bills'],
                'device_id' => $request->header('X-Device-Id'),
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'referenceNumber' => $transaction->reference_number,
                'status' => 'PROCESSING',
                'message' => 'Bill payment is being processed.',
                'balance' => $retailer->fresh()->walletBalances()['combined'],
                'eloadBalance' => $retailer->fresh()->walletBalances()['eload'],
                'billsBalance' => $retailer->fresh()->walletBalances()['bills'],
            ]);
        });
    }

    public function processEcash(Request $request): JsonResponse
    {
        $request->validate([
            'provider_code' => 'required|string',
            'mobile_number' => 'required|string|min:10',
            'amount' => 'required|numeric|min:1',
            'reference_id' => 'nullable|string',
        ]);

        $retailer = $request->attributes->get('retailer');
        $fee = 0;
        $totalCost = $request->amount + $fee;

        if (!$retailer->hasSufficientBalance($totalCost, 'bills')) {
            $wallets = $retailer->walletBalances();
            return response()->json([
                'success' => false,
                'message' => 'Insufficient Bills/Cash-In wallet balance. Current: ₱' . number_format($wallets['bills'], 2),
            ], 400);
        }

        return DB::transaction(function () use ($retailer, $request, $totalCost, $fee) {
            $balanceBefore = $retailer->walletBalances()['bills'];
            $retailer->deductBalance($totalCost, 'bills');

            $transaction = Transaction::create([
                'retailer_id' => $retailer->id,
                'type' => 'ECASH',
                'reference_number' => $this->generateRefNumber(),
                'provider_code' => $request->provider_code,
                'product_name' => 'Cash-In',
                'target_number' => $request->mobile_number,
                'amount' => $request->amount,
                'fee' => $fee,
                'retailer_cost' => $totalCost,
                'status' => 'PROCESSING',
                'payment_method' => 'WALLET',
                'balance_before' => $balanceBefore,
                'balance_after' => $retailer->fresh()->walletBalances()['bills'],
                'device_id' => $request->header('X-Device-Id'),
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'referenceNumber' => $transaction->reference_number,
                'status' => 'PROCESSING',
                'message' => 'Cash-in is being processed.',
                'balance' => $retailer->fresh()->walletBalances()['combined'],
                'eloadBalance' => $retailer->fresh()->walletBalances()['eload'],
                'billsBalance' => $retailer->fresh()->walletBalances()['bills'],
            ]);
        });
    }

    public function processRfid(Request $request): JsonResponse
    {
        $request->validate([
            'provider_code' => 'required|string',
            'account_number' => 'required|string|min:4|max:32',
            'amount' => 'required|numeric|min:1',
            'reference_id' => 'nullable|string',
            'tag_id' => 'nullable|string|max:64',
        ]);

        $retailer = $request->attributes->get('retailer');
        $product = Product::where('code', $request->provider_code . '_RELOAD')->active()->first();
        $fee = $product?->fee ?? 0;
        $totalCost = $request->amount + $fee;

        if (!$retailer->hasSufficientBalance($totalCost, 'eload')) {
            $wallets = $retailer->walletBalances();
            return response()->json([
                'success' => false,
                'message' => 'Insufficient E-Load wallet balance. Current: ₱' . number_format($wallets['eload'], 2),
            ], 400);
        }

        return DB::transaction(function () use ($retailer, $request, $totalCost, $fee, $product) {
            $balanceBefore = $retailer->walletBalances()['eload'];
            $retailer->deductBalance($totalCost, 'eload');

            $transaction = Transaction::create([
                'retailer_id' => $retailer->id,
                'product_id' => $product?->id,
                'type' => 'RFID',
                'reference_number' => $this->generateRefNumber(),
                'provider_code' => $request->provider_code,
                'product_code' => $product?->code,
                'product_name' => $product?->name ?? 'RFID Reload',
                'target_number' => $request->account_number,
                'amount' => $request->amount,
                'fee' => $fee,
                'retailer_cost' => $totalCost,
                'status' => 'PROCESSING',
                'payment_method' => 'WALLET',
                'balance_before' => $balanceBefore,
                'balance_after' => $retailer->fresh()->walletBalances()['eload'],
                'device_id' => $request->header('X-Device-Id'),
                'ip_address' => $request->ip(),
                'remarks' => $request->tag_id,
            ]);

            return response()->json([
                'success' => true,
                'referenceNumber' => $transaction->reference_number,
                'status' => 'PROCESSING',
                'message' => 'RFID reload is being processed.',
                'balance' => $retailer->fresh()->walletBalances()['combined'],
                'eloadBalance' => $retailer->fresh()->walletBalances()['eload'],
                'billsBalance' => $retailer->fresh()->walletBalances()['bills'],
            ]);
        });
    }

    public function history(Request $request): JsonResponse
    {
        $retailer = $request->attributes->get('retailer');
        $page = $request->integer('page', 1);
        $limit = min($request->integer('limit', 50), 100);
        $type = $request->query('type');

        $query = $retailer->transactions()->orderByDesc('created_at');

        if ($type && $type !== 'ALL') {
            $query->where('type', $type);
        }

        $total = $query->count();
        $transactions = $query->skip(($page - 1) * $limit)->take($limit)->get();

        return response()->json([
            'success' => true,
            'transactions' => $transactions->map(fn ($t) => [
                'id' => (string) $t->id,
                'type' => $t->type,
                'provider' => $t->provider_code,
                'product' => $t->product_name,
                'amount' => (float) $t->amount,
                'fee' => (float) $t->fee,
                'targetNumber' => $t->target_number,
                'referenceNumber' => $t->reference_number,
                'status' => $t->status,
                'createdAt' => $t->created_at->toISOString(),
            ]),
            'totalPages' => ceil($total / $limit),
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            '*.localId' => 'required|integer',
            '*.type' => 'required|string',
            '*.referenceNumber' => 'required|string',
            '*.amount' => 'required|numeric',
            '*.status' => 'required|string',
            '*.createdAt' => 'required|integer',
        ]);

        $retailer = $request->attributes->get('retailer');
        $synced = 0;

        foreach ($request->all() as $item) {
            $exists = Transaction::where('reference_number', $item['referenceNumber'])->exists();
            if (!$exists) {
                Transaction::create([
                    'retailer_id' => $retailer->id,
                    'type' => $item['type'],
                    'reference_number' => $item['referenceNumber'],
                    'provider_code' => $item['type'],
                    'target_number' => '',
                    'amount' => $item['amount'],
                    'status' => $item['status'],
                    'created_at' => date('Y-m-d H:i:s', $item['createdAt'] / 1000),
                ]);
                $synced++;
            }
        }

        return response()->json([
            'success' => true,
            'syncedCount' => $synced,
            'message' => "$synced transactions synced.",
        ]);
    }

    private function generateRefNumber(): string
    {
        return 'EP' . now()->format('ymdHis') . strtoupper(Str::random(4));
    }
}
