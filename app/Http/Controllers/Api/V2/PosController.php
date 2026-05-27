<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Concerns\ResolvesEpayRetailer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosController extends Controller
{
    use ResolvesEpayRetailer;

    public function catalog(Request $request): JsonResponse
    {
        $this->retailerFromApi($request);

        return response()->json([
            'success' => true,
            'services' => [
                ['key' => 'eload', 'label' => 'E-Load', 'route' => 'eload'],
                ['key' => 'bills', 'label' => 'Bills Payment', 'route' => 'bills'],
                ['key' => 'ecash', 'label' => 'Cash-in', 'route' => 'ecash'],
                ['key' => 'rfid', 'label' => 'RFID', 'route' => 'rfid'],
            ],
        ]);
    }
}
