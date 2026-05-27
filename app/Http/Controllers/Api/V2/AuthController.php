<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Retailer;
use App\Support\PhilippineMobile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'mobile_number' => 'required_without:account_id|nullable|string|max:20',
            'account_id' => 'nullable|string',
            'pin' => 'required|string|min:4',
            'device_id' => 'nullable|string',
        ]);

        $retailer = null;

        if ($request->filled('mobile_number')) {
            $normalized = PhilippineMobile::normalize($request->mobile_number);

            if (! PhilippineMobile::isValid($normalized)) {
                throw ValidationException::withMessages([
                    'mobile_number' => ['Enter a valid Philippine mobile number (e.g. 09171234567).'],
                ]);
            }

            $retailer = Retailer::where('mobile_number', $normalized)->first();
        } elseif ($request->filled('account_id')) {
            $retailer = Retailer::where('account_id', $request->account_id)->first();
        }

        if (! $retailer || ! Hash::check($request->pin, $retailer->pin)) {
            $message = $request->filled('account_id') && ! $request->filled('mobile_number')
                ? 'Invalid account ID or PIN.'
                : 'Invalid mobile number or PIN.';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 401);
        }

        if (! $retailer->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account has been deactivated. Contact admin.',
            ], 403);
        }

        $token = Str::random(64);
        $retailer->update([
            'api_token' => hash('sha256', $token),
            'device_id' => $request->device_id,
            'last_login_at' => now(),
        ]);

        $wallets = $retailer->walletBalances();

        return response()->json([
            'success' => true,
            'token' => $token,
            'account' => [
                'id' => $retailer->account_id,
                'businessName' => $retailer->business_name,
                'ownerName' => $retailer->owner_name,
                'mobileNumber' => $retailer->mobile_number,
                'email' => $retailer->email,
                'balance' => $wallets['combined'],
                'eloadBalance' => $wallets['eload'],
                'billsBalance' => $wallets['bills'],
                'isKioskEnabled' => $retailer->is_kiosk_enabled,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $retailer = $request->attributes->get('retailer');
        $retailer->update(['api_token' => null]);

        return response()->json(['success' => true, 'message' => 'Logged out.']);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'mobile_number' => 'required|string|max:15|unique:epay_retailers,mobile_number',
            'email' => 'nullable|email|unique:epay_retailers,email',
            'address' => 'nullable|string',
            'pin' => 'required|string|min:4|max:6',
        ]);

        $accountId = 'EP'.strtoupper(Str::random(8));

        $retailer = Retailer::create([
            'account_id' => $accountId,
            'business_name' => $request->business_name,
            'owner_name' => $request->owner_name,
            'mobile_number' => PhilippineMobile::normalize($request->mobile_number),
            'email' => $request->email,
            'address' => $request->address,
            'pin' => Hash::make($request->pin),
            'balance' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Your Account ID: '.$accountId,
            'account_id' => $accountId,
        ], 201);
    }

    public function changePin(Request $request): JsonResponse
    {
        $request->validate([
            'current_pin' => 'required|string',
            'new_pin' => 'required|string|min:4|max:6',
        ]);

        $retailer = $request->attributes->get('retailer');

        if (! Hash::check($request->current_pin, $retailer->pin)) {
            return response()->json([
                'success' => false,
                'message' => 'Current PIN is incorrect.',
            ], 400);
        }

        $retailer->update(['pin' => Hash::make($request->new_pin)]);

        return response()->json(['success' => true, 'message' => 'PIN changed successfully.']);
    }
}
