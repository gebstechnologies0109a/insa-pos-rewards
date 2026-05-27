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
        $wallets = $retailer->walletBalances();

        return response()->json([
            'success' => true,
            'wallets' => [
                'eload' => [
                    'label' => 'E-Load Wallet',
                    'balance' => $wallets['eload'],
                    'currency' => 'PHP',
                ],
                'bills' => [
                    'label' => 'Bills & Cash-In Wallet',
                    'balance' => $wallets['bills'],
                    'currency' => 'PHP',
                ],
                'total' => $wallets['combined'],
            ],
        ]);
    }
}
