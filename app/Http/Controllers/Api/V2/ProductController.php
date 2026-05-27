<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\Product;
use App\Models\EPayPlus\Provider;
use App\Models\EPayPlus\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function eloadProducts(Request $request): JsonResponse
    {
        return $this->getProductsByType('ELOAD', billingType: 'prepaid');
    }

    public function billsProducts(Request $request): JsonResponse
    {
        return $this->getProductsByType('BILLS', billingType: 'postpaid');
    }

    public function ecashProducts(Request $request): JsonResponse
    {
        return $this->getProductsByType('ECASH', billingType: 'prepaid');
    }

    public function rfidProducts(Request $request): JsonResponse
    {
        return $this->getProductsByType('RFID', billingType: 'prepaid');
    }

    public function providers(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $billingType = $request->query('billing_type');

        $query = Provider::active()->orderBy('sort_order');
        if ($type) {
            $query->ofType($type);
        }
        if ($billingType) {
            $query->where('billing_type', $billingType);
        }

        $providers = $query->get()->map(fn ($p) => [
            'code' => $p->code,
            'name' => $p->name,
            'type' => $p->type,
            'category' => $p->category,
            'billingType' => $p->billing_type,
            'logoUrl' => $p->logo_url,
        ]);

        return response()->json(['success' => true, 'providers' => $providers]);
    }

    public function announcements(Request $request): JsonResponse
    {
        $announcements = Announcement::active()
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($a) => [
                'id' => (string) $a->id,
                'title' => $a->title,
                'content' => $a->content,
                'type' => $a->type,
                'createdAt' => $a->created_at->toISOString(),
            ]);

        return response()->json(['success' => true, 'announcements' => $announcements]);
    }

    private function getProductsByType(string $type, ?string $billingType = null): JsonResponse
    {
        $query = Product::active()->ofType($type)->with('provider');

        if ($billingType) {
            $query->where(function ($q) use ($billingType) {
                $q->where('billing_type', $billingType)
                    ->orWhereNull('billing_type');
            });
        }

        // Never expose prepaid load SKUs on bills endpoints (and vice versa).
        if ($type === 'BILLS') {
            $query->where('amount', '<=', 0);
        }

        $products = $query
            ->orderBy('sort_order')
            ->get()
            ->filter(function ($p) use ($type, $billingType) {
                if ($p->provider && $p->provider->type !== $type) {
                    return false;
                }
                if ($billingType && $p->provider?->billing_type && $p->provider->billing_type !== $billingType) {
                    return false;
                }
                if ($type === 'BILLS' && $p->provider?->billing_type === 'prepaid') {
                    return false;
                }
                if ($type === 'ELOAD' && $p->provider?->billing_type === 'postpaid') {
                    return false;
                }

                return true;
            })
            ->values()
            ->map(fn ($p) => [
                'code' => $p->code,
                'name' => $p->name,
                'providerCode' => $p->provider?->code ?? $p->provider_id,
                'providerName' => $p->provider?->name ?? '',
                'amount' => (float) $p->amount,
                'fee' => (float) $p->fee,
                'description' => $p->description ?? '',
                'keyword' => $p->keyword ?? '',
                'category' => $this->productCategory($p),
                'productKind' => $p->product_kind ?? 'regular',
                'billingType' => $p->billing_type ?? $p->provider?->billing_type,
                'validityDays' => $p->validity_days,
            ]);

        return response()->json(['success' => true, 'products' => $products]);
    }

    private function productCategory(Product $product): string
    {
        if (($product->product_kind ?? 'regular') === 'promo') {
            return 'Promo';
        }

        if ($product->type === 'ELOAD') {
            return 'Prepaid Load';
        }

        return $product->provider?->category ?? '';
    }
}
