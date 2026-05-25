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

    /**
     * Quick lookup — auto-detects type from a single query string.
     * Used by the POS cashier UI for both cart customer search and rewards scan.
     */
    public function quickLookup(\Illuminate\Http\Request $request): JsonResponse
    {
        $query = trim($request->input('query', ''));
        if (! $query) {
            return response()->json(['success' => false, 'customers' => []], 422);
        }

        $customers = collect();

        if (preg_match('/^[0-9a-f]{8}-/i', $query) || str_starts_with($query, 'diybiz:')) {
            $c = $this->lookupService->resolve('qr', $query);
            if ($c instanceof Customer) $customers->push($c);
            elseif ($c instanceof Collection) $customers = $c;
        }

        if ($customers->isEmpty() && preg_match('/^\d{5,}$/', $query)) {
            $c = $this->lookupService->resolve('barcode', $query);
            if ($c) $customers->push($c);
        }

        if ($customers->isEmpty() && preg_match('/^[\d+() -]{7,}$/', $query)) {
            $c = $this->lookupService->resolve('phone', $query);
            if ($c) $customers->push($c);
        }

        if ($customers->isEmpty()) {
            $c = $this->lookupService->resolve('search', $query);
            if ($c instanceof Collection) $customers = $c;
        }

        return response()->json([
            'success'   => $customers->isNotEmpty(),
            'customers' => $customers->map(fn (Customer $c) => $this->formatCustomer($c))->values(),
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
