<?php

namespace App\Http\Controllers\Concerns;

use App\Models\EPayPlus\Retailer;
use Illuminate\Http\Request;

trait ResolvesEpayRetailer
{
    protected function retailerFromApi(Request $request): Retailer
    {
        $retailer = $request->attributes->get('retailer');
        if (!$retailer instanceof Retailer) {
            abort(401, 'Retailer not authenticated.');
        }

        return $retailer;
    }

    protected function resolveWebRetailerId(Request $request): int
    {
        if ($request->filled('retailer_id')) {
            $id = (int) $request->input('retailer_id');
            session(['epay_pos_retailer_id' => $id]);

            return $id;
        }

        if (session()->has('epay_pos_retailer_id')) {
            return (int) session('epay_pos_retailer_id');
        }

        $id = Retailer::where('is_active', true)->orderBy('business_name')->value('id');
        if ($id) {
            session(['epay_pos_retailer_id' => $id]);
        }

        return (int) ($id ?? 0);
    }

    protected function webRetailer(Request $request): ?Retailer
    {
        $id = $this->resolveWebRetailerId($request);
        if ($id <= 0) {
            return null;
        }

        return Retailer::find($id);
    }
}
