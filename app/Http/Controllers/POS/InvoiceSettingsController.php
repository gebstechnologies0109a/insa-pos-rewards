<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Models\POS\InvoiceSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        $settings = InvoiceSetting::forBranch($branchId);

        return response()->json([
            'success'  => true,
            'settings' => $settings->only([
                'store_name',
                'contact_number',
                'store_address',
                'invoice_header',
                'invoice_footer',
                'tax_id',
            ]),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! in_array($user->role, ['owner', 'admin', 'manager', 'supervisor'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to edit invoice settings.',
            ], 403);
        }

        $validated = $request->validate([
            'store_name'      => 'nullable|string|max:255',
            'contact_number'  => 'nullable|string|max:100',
            'store_address'   => 'nullable|string|max:1000',
            'invoice_header'  => 'nullable|string|max:1000',
            'invoice_footer'  => 'nullable|string|max:1000',
            'tax_id'          => 'nullable|string|max:100',
        ]);

        $settings = InvoiceSetting::forBranch($user->branch_id);
        $settings->update($validated);

        return response()->json([
            'success'  => true,
            'message'  => 'Invoice settings saved successfully.',
            'settings' => $settings->only([
                'store_name',
                'contact_number',
                'store_address',
                'invoice_header',
                'invoice_footer',
                'tax_id',
            ]),
        ]);
    }
}
