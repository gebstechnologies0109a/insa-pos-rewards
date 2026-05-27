<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Blacklist;
use App\Models\EPayPlus\Device;
use App\Models\EPayPlus\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /**
     * Dual-wallet balances for authenticated retailer.
     */
    public function index(Request $request): JsonResponse
    {
        $retailer = $request->attributes->get('retailer');

        return response()->json([
            'success' => true,
            'wallets' => [
                'eload' => [
                    'label' => 'E-Load Wallet',
                    'balance' => (float) ($retailer->eload_balance ?? $retailer->balance),
                    'currency' => 'PHP',
                ],
                'bills' => [
                    'label' => 'Bills & Cash-In Wallet',
                    'balance' => (float) ($retailer->bills_balance ?? 0),
                    'currency' => 'PHP',
                ],
                'total' => (float) (($retailer->eload_balance ?? $retailer->balance) + ($retailer->bills_balance ?? 0)),
            ],
        ]);
    }
}
