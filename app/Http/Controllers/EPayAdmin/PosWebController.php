<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Concerns\ResolvesEpayRetailer;
use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Retailer;
use Illuminate\Http\Request;

class PosWebController extends Controller
{
    use ResolvesEpayRetailer;

    public function index(Request $request)
    {
        $retailerId = $this->resolveWebRetailerId($request);
        $retailers = Retailer::where('is_active', true)->orderBy('business_name')->get(['id', 'business_name', 'account_id']);
        $retailer = Retailer::find($retailerId);

        return view('epayplus.pos.index', compact('retailers', 'retailerId', 'retailer'));
    }
}
