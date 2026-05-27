<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Topup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function balance(Request $request): JsonResponse
    {
        $retailer = $request->attributes->get('retailer');

        return response()->json([
            'success' => true,
            'balance' => (float) $retailer->balance,
            'eload_balance' => (float) ($retailer->eload_balance ?? $retailer->balance),
            'bills_balance' => (float) ($retailer->bills_balance ?? 0),
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        $retailer = $request->attributes->get('retailer');

        return response()->json([
            'success' => true,
            'account' => [
                'id' => $retailer->account_id,
                'businessName' => $retailer->business_name,
                'ownerName' => $retailer->owner_name,
                'mobileNumber' => $retailer->mobile_number,
                'email' => $retailer->email,
                'address' => $retailer->address,
                'balance' => (float) $retailer->balance,
                'eloadBalance' => (float) ($retailer->eload_balance ?? $retailer->balance),
                'billsBalance' => (float) ($retailer->bills_balance ?? 0),
                'isKioskEnabled' => $retailer->is_kiosk_enabled,
                'printerAddress' => $retailer->printer_address,
                'printerType' => $retailer->printer_type,
                'simSlot' => $retailer->sim_slot,
                'createdAt' => $retailer->created_at->toISOString(),
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'owner_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|nullable|email',
            'address' => 'sometimes|nullable|string',
            'printer_address' => 'sometimes|nullable|string',
            'printer_type' => 'sometimes|in:BLUETOOTH,USB,SERIAL',
            'sim_slot' => 'sometimes|integer|min:0|max:1',
            'is_kiosk_enabled' => 'sometimes|boolean',
            'kiosk_pin' => 'sometimes|nullable|string|min:4',
        ]);

        $retailer = $request->attributes->get('retailer');
        $retailer->update($request->only([
            'business_name', 'owner_name', 'email', 'address',
            'printer_address', 'printer_type', 'sim_slot',
            'is_kiosk_enabled', 'kiosk_pin',
        ]));

        return response()->json(['success' => true, 'message' => 'Profile updated.']);
    }

    public function requestTopup(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
            'payment_method' => 'required|in:GCASH,BANK_TRANSFER,CASH,MAYA',
            'reference_number' => 'nullable|string',
        ]);

        $retailer = $request->attributes->get('retailer');

        $topup = Topup::create([
            'retailer_id' => $retailer->id,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'reference_number' => $request->reference_number,
            'balance_before' => $retailer->balance,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Top-up request submitted. Wait for admin approval.',
            'topup_id' => $topup->id,
        ]);
    }

    public function topupHistory(Request $request): JsonResponse
    {
        $retailer = $request->attributes->get('retailer');

        $topups = $retailer->topups()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'amount' => (float) $t->amount,
                'paymentMethod' => $t->payment_method,
                'referenceNumber' => $t->reference_number,
                'status' => $t->status,
                'createdAt' => $t->created_at->toISOString(),
                'approvedAt' => $t->approved_at?->toISOString(),
            ]);

        return response()->json(['success' => true, 'topups' => $topups]);
    }
}
