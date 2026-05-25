<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Http\Requests\POS\LookupRequest;
use App\Models\POS\Customer;
use App\Services\POS\CustomerLookupService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

class CustomerLookupController extends Controller
{
    public function __construct(
        protected CustomerLookupService $lookupService,
    ) {}

    public function lookup(LookupRequest $request): JsonResponse
    {
        $result = $this->lookupService->resolve(
            $request->validated('type'),
            $request->validated('value'),
        );

        if ($result === null) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found.',
                'data'    => null,
            ], 404);
        }

        if ($result instanceof Collection) {
            return response()->json([
                'success' => true,
                'message' => "{$result->count()} customer(s) found.",
                'data'    => $result->map(fn (Customer $c) => $this->formatCustomer($c)),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Customer found.',
            'data'    => $this->formatCustomer($result),
        ]);
    }

    protected function formatCustomer(Customer $customer): array
    {
        return [
            'uuid'           => $customer->uuid,
            'card_number'    => $customer->card_number,
            'first_name'     => $customer->first_name,
            'last_name'      => $customer->last_name,
            'full_name'      => $customer->full_name,
            'phone'          => $customer->phone,
            'email'          => $customer->email,
            'loyalty_points' => $customer->loyalty_points,
            'status'         => $customer->status,
        ];
    }
}
