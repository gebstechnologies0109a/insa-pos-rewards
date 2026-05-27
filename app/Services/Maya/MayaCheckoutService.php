<?php

namespace App\Services\Maya;

use App\Models\EPayPlus\MayaCheckoutSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MayaCheckoutService
{
    public function __construct(
        private readonly MayaIntegrationConfigService $integrationConfig
    ) {}

    /**
     * @param  array{amount: float, description?: string, retailer_id?: int|null}  $input
     * @return array{success: bool, demo: bool, checkout_id: string|null, redirect_url: string|null, reference: string, message?: string}
     */
    public function createCheckout(array $input): array
    {
        $amount = round((float) ($input['amount'] ?? 0), 2);
        if ($amount < 1) {
            return [
                'success' => false,
                'demo' => true,
                'checkout_id' => null,
                'redirect_url' => null,
                'reference' => '',
                'message' => 'Amount must be at least PHP 1.00',
            ];
        }

        $reference = 'EPAY-'.strtoupper(Str::random(10));
        $description = $input['description'] ?? 'ePayPlus Maya Checkout';

        if ($this->integrationConfig->checkoutDemoMode()) {
            $redirect = url('/epayplus/integrations/maya-negosyo?checkout_demo=1&ref='.$reference);

            $session = MayaCheckoutSession::query()->create([
                'reference_number' => $reference,
                'amount' => $amount,
                'currency' => 'PHP',
                'status' => 'demo_pending',
                'redirect_url' => $redirect,
                'request_payload' => ['description' => $description, 'demo' => true],
                'retailer_id' => $input['retailer_id'] ?? null,
            ]);

            return [
                'success' => true,
                'demo' => true,
                'checkout_id' => 'demo-'.$session->id,
                'redirect_url' => $redirect,
                'reference' => $reference,
                'message' => 'Demo mode — configure MAYA_CHECKOUT_PUBLIC_KEY and MAYA_CHECKOUT_SECRET_KEY for live Checkout.',
            ];
        }

        $baseUrl = rtrim($this->baseUrl(), '/');
        $payload = [
            'totalAmount' => [
                'value' => $amount,
                'currency' => 'PHP',
            ],
            'requestReferenceNumber' => $reference,
            'redirectUrl' => [
                'success' => config('maya_checkout.redirect_success_url') ?: url('/epayplus/integrations/maya-negosyo'),
                'failure' => config('maya_checkout.redirect_failure_url') ?: url('/epayplus/integrations/maya-negosyo'),
                'cancel' => config('maya_checkout.redirect_cancel_url') ?: url('/epayplus/integrations/maya-negosyo'),
            ],
            'buyer' => [
                'firstName' => 'ePayPlus',
                'lastName' => 'Merchant',
            ],
            'items' => [
                [
                    'name' => Str::limit($description, 120),
                    'quantity' => 1,
                    'totalAmount' => [
                        'value' => $amount,
                        'currency' => 'PHP',
                    ],
                ],
            ],
        ];

        $response = Http::timeout((int) config('maya_checkout.http_timeout', 30))
            ->withBasicAuth((string) config('maya_checkout.public_key'), (string) config('maya_checkout.secret_key'))
            ->post($baseUrl.config('maya_checkout.checkout_path'), $payload);

        if (! $response->successful()) {
            return [
                'success' => false,
                'demo' => false,
                'checkout_id' => null,
                'redirect_url' => null,
                'reference' => $reference,
                'message' => 'Maya Checkout API error: '.$response->status(),
            ];
        }

        $body = $response->json() ?? [];
        $checkoutId = $body['checkoutId'] ?? $body['id'] ?? null;
        $redirectUrl = $body['redirectUrl'] ?? null;

        MayaCheckoutSession::query()->create([
            'checkout_id' => $checkoutId,
            'reference_number' => $reference,
            'amount' => $amount,
            'currency' => 'PHP',
            'status' => 'pending',
            'redirect_url' => $redirectUrl,
            'request_payload' => $payload,
            'retailer_id' => $input['retailer_id'] ?? null,
        ]);

        return [
            'success' => true,
            'demo' => false,
            'checkout_id' => $checkoutId,
            'redirect_url' => $redirectUrl,
            'reference' => $reference,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload): void
    {
        $reference = $payload['requestReferenceNumber']
            ?? $payload['referenceNumber']
            ?? $payload['reference_number']
            ?? null;

        $checkoutId = $payload['checkoutId'] ?? $payload['id'] ?? null;
        $status = $payload['paymentStatus'] ?? $payload['status'] ?? 'unknown';

        $query = MayaCheckoutSession::query();
        if ($reference) {
            $session = $query->where('reference_number', $reference)->first();
        } elseif ($checkoutId) {
            $session = $query->where('checkout_id', $checkoutId)->first();
        } else {
            return;
        }

        if (! $session) {
            MayaCheckoutSession::query()->create([
                'checkout_id' => is_string($checkoutId) ? $checkoutId : null,
                'reference_number' => is_string($reference) ? $reference : 'WH-'.Str::upper(Str::random(8)),
                'amount' => 0,
                'status' => strtolower((string) $status),
                'webhook_payload' => $payload,
            ]);

            return;
        }

        $session->update([
            'status' => strtolower((string) $status),
            'webhook_payload' => $payload,
            'checkout_id' => $session->checkout_id ?: (is_string($checkoutId) ? $checkoutId : null),
        ]);
    }

    private function baseUrl(): string
    {
        return config('maya_checkout.environment') === 'production'
            ? (string) config('maya_checkout.production_base_url')
            : (string) config('maya_checkout.sandbox_base_url');
    }
}
