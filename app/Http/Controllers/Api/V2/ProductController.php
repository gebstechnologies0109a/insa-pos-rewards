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
        return $this->getProductsByType('ELOAD');
    }

    public function billsProducts(Request $request): JsonResponse
    {
        return $this->getProductsByType('BILLS');
    }

    public function ecashProducts(Request $request): JsonResponse
    {
        return $this->getProductsByType('ECASH');
    }

    public function providers(Request $request): JsonResponse
    {
        $type = $request->query('type');

        $query = Provider::active()->orderBy('sort_order');
        if ($type) {
            $query->ofType($type);
        }

        $providers = $query->get()->map(fn ($p) => [
            'code' => $p->code,
            'name' => $p->name,
            'type' => $p->type,
            'category' => $p->category,
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

    private function getProductsByType(string $type): JsonResponse
    {
        $products = Product::active()
            ->ofType($type)
            ->with('provider')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($p) => [
                'code' => $p->code,
                'name' => $p->name,
                'providerCode' => $p->provider?->code ?? $p->provider_id,
                'providerName' => $p->provider?->name ?? '',
                'amount' => (float) $p->amount,
                'fee' => (float) $p->fee,
                'description' => $p->description ?? '',
                'keyword' => $p->keyword ?? '',
                'category' => $p->provider?->category ?? '',
            ]);

        return response()->json(['success' => true, 'products' => $products]);
    }
}
