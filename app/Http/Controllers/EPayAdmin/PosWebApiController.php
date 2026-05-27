<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Concerns\ResolvesEpayRetailer;
use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Product;
use App\Models\EPayPlus\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosWebApiController extends Controller
{
    use ResolvesEpayRetailer;

    public function providers(Request $request): JsonResponse
    {
        $type = $this->normalizeType($request->query('type'));
        if (!$type) {
            return response()->json(['success' => false, 'message' => 'type is required'], 422);
        }

        $billingType = match ($type) {
            'BILLS' => 'postpaid',
            'ELOAD' => 'prepaid',
            default => null,
        };

        $query = Provider::active()->ofType($type)->orderBy('sort_order');
        if ($billingType) {
            $query->where('billing_type', $billingType);
        }

        $providers = $query->get()->map(fn ($p) => [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'category' => $p->category,
            'logoUrl' => $p->logo_url,
        ]);

        return response()->json(['success' => true, 'providers' => $providers]);
    }

    public function products(Request $request): JsonResponse
    {
        $type = $this->normalizeType($request->query('type'));
        $providerId = $request->integer('provider_id');

        if (!$type || $providerId <= 0) {
            return response()->json(['success' => false, 'message' => 'type and provider_id are required'], 422);
        }

        $provider = Provider::active()->find($providerId);
        if (!$provider || $provider->type !== $type) {
            return response()->json(['success' => false, 'message' => 'Provider not found'], 404);
        }

        $products = $this->productsQuery($type, $providerId)->values();

        return response()->json(['success' => true, 'provider' => [
            'id' => $provider->id,
            'code' => $provider->code,
            'name' => $provider->name,
        ], 'products' => $products]);
    }

    public function billCategories(): JsonResponse
    {
        $categories = Product::active()
            ->ofType('BILLS')
            ->with('provider')
            ->where('amount', '<=', 0)
            ->get()
            ->filter(fn ($p) => $this->isValidBillProduct($p))
            ->map(fn ($p) => $this->productCategory($p))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return response()->json(['success' => true, 'categories' => $categories]);
    }

    public function billBillers(Request $request): JsonResponse
    {
        $category = $request->query('category');
        if (!$category) {
            return response()->json(['success' => false, 'message' => 'category is required'], 422);
        }

        $billers = Product::active()
            ->ofType('BILLS')
            ->with('provider')
            ->where('amount', '<=', 0)
            ->get()
            ->filter(fn ($p) => $this->isValidBillProduct($p) && $this->productCategory($p) === $category)
            ->sortBy('name')
            ->values()
            ->map(fn ($p) => $this->formatProduct($p));

        return response()->json(['success' => true, 'category' => $category, 'billers' => $billers]);
    }

    public function balance(Request $request): JsonResponse
    {
        $retailer = $this->webRetailer($request);
        if (!$retailer) {
            return response()->json(['success' => false, 'message' => 'No retailer selected'], 404);
        }

        $wallets = $retailer->walletBalances();

        return response()->json([
            'success' => true,
            'retailer' => [
                'id' => $retailer->id,
                'name' => $retailer->business_name,
            ],
            'balances' => [
                'eload' => $wallets['eload'],
                'bills' => $wallets['bills'],
                'combined' => $wallets['combined'],
            ],
        ]);
    }

    private function productsQuery(string $type, int $providerId)
    {
        $billingType = match ($type) {
            'BILLS' => 'postpaid',
            'ELOAD' => 'prepaid',
            default => null,
        };

        $query = Product::active()
            ->ofType($type)
            ->where('provider_id', $providerId)
            ->with('provider')
            ->orderBy('sort_order');

        if ($billingType) {
            $query->where(function ($q) use ($billingType) {
                $q->where('billing_type', $billingType)->orWhereNull('billing_type');
            });
        }

        if ($type === 'BILLS') {
            $query->where('amount', '<=', 0);
        }

        return $query->get()
            ->filter(function ($p) use ($type, $billingType) {
                if ($type === 'BILLS' && !$this->isValidBillProduct($p)) {
                    return false;
                }
                if ($type === 'ELOAD' && $p->provider?->billing_type === 'postpaid') {
                    return false;
                }
                if ($billingType && $p->provider?->billing_type && $p->provider->billing_type !== $billingType) {
                    return false;
                }

                return true;
            })
            ->map(fn ($p) => $this->formatProduct($p));
    }

    private function formatProduct(Product $p): array
    {
        $amount = (float) $p->amount;
        $needsAmount = $p->type === 'BILLS' || $amount <= 0;

        return [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'providerId' => $p->provider_id,
            'providerCode' => $p->provider?->code ?? '',
            'providerName' => $p->provider?->name ?? '',
            'amount' => $amount,
            'fee' => (float) $p->fee,
            'description' => $p->description ?? '',
            'category' => $this->productCategory($p),
            'productKind' => $p->product_kind ?? 'regular',
            'needsCustomAmount' => $needsAmount,
            'needsPhone' => in_array($p->type, ['ELOAD', 'ECASH'], true),
            'needsAccount' => in_array($p->type, ['BILLS', 'RFID'], true),
        ];
    }

    private function isValidBillProduct(Product $p): bool
    {
        if ($p->provider?->type !== 'BILLS') {
            return false;
        }
        if ($p->provider?->billing_type === 'prepaid') {
            return false;
        }

        return true;
    }

    private function productCategory(Product $product): string
    {
        if (($product->product_kind ?? 'regular') === 'promo') {
            return 'Promo';
        }

        if ($product->type === 'ELOAD') {
            return 'Prepaid Load';
        }

        return $product->provider?->category ?? $product->category ?? 'Others';
    }

    private function normalizeType(?string $type): ?string
    {
        if (!$type) {
            return null;
        }

        $type = strtoupper(trim($type));

        return in_array($type, ['ELOAD', 'BILLS', 'ECASH', 'RFID'], true) ? $type : null;
    }
}
