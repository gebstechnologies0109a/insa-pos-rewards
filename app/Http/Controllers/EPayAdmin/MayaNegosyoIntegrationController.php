<?php

namespace App\Http\Controllers\EPayAdmin;

use App\Http\Controllers\Controller;
use App\Models\EPayPlus\MayaBillerTransaction;
use App\Models\EPayPlus\MayaCheckoutSession;
use App\Models\EPayPlus\Retailer;
use App\Models\EPayPlus\Transaction;
use App\Services\Maya\MayaCheckoutService;
use App\Services\Maya\MayaIntegrationConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MayaNegosyoIntegrationController extends Controller
{
    public function __construct(
        private readonly MayaIntegrationConfigService $integrationConfig,
        private readonly MayaCheckoutService $checkoutService
    ) {}

    public function index(Request $request): View
    {
        $apiConfig = $this->integrationConfig->toApiPayload();
        $publicBase = rtrim((string) config('maya_biller.public_base_url', config('app.url')), '/');

        $walletStats = [
            'total_eload' => (float) Retailer::sum('eload_balance'),
            'total_bills' => (float) Retailer::sum('bills_balance'),
            'total_combined' => (float) Retailer::sum('balance'),
        ];

        $features = [
            ['icon' => 'bi-receipt', 'title' => 'Bills via Maya', 'desc' => 'Customers pay ePayPlus billers inside Maya / Negosyo apps (Partner Biller inbound).', 'enabled' => $apiConfig['biller_enabled'], 'link' => route('epayplus.integrations.maya')],
            ['icon' => 'bi-cash-coin', 'title' => 'Cash-In', 'desc' => 'Retailer cash-in flows via ePayPlus Android / web operations.', 'enabled' => true, 'link' => route('epayplus.transactions')],
            ['icon' => 'bi-phone', 'title' => 'E-Load', 'desc' => 'Prepaid load products from ePayPlus catalog.', 'enabled' => true, 'link' => route('epayplus.products')],
            ['icon' => 'bi-qr-code-scan', 'title' => 'Maya Checkout / QR', 'desc' => 'Accept customer payments via Maya Checkout (Pay with Maya).', 'enabled' => $apiConfig['checkout_enabled'] || $apiConfig['checkout_demo_mode'], 'link' => null],
            ['icon' => 'bi-clock-history', 'title' => 'Transactions', 'desc' => 'ePayPlus transaction ledger and Maya Biller callback states.', 'enabled' => true, 'link' => route('epayplus.transactions')],
            ['icon' => 'bi-wallet2', 'title' => 'Dual Wallets', 'desc' => 'E-Load wallet + Bills / Cash-In wallet per retailer.', 'enabled' => true, 'link' => route('epayplus.retailers')],
        ];

        $recentCheckout = MayaCheckoutSession::query()->latest()->limit(10)->get();
        $mayaBillerCount = MayaBillerTransaction::query()->whereDate('created_at', today())->count();
        $todaySales = Transaction::today()->successful()->sum('amount');

        return view('epayplus.integrations.maya-negosyo', [
            'apiConfig' => $apiConfig,
            'publicBase' => $publicBase,
            'walletStats' => $walletStats,
            'features' => $features,
            'recentCheckout' => $recentCheckout,
            'mayaBillerToday' => $mayaBillerCount,
            'todaySales' => $todaySales,
            'negosyoDeepLink' => config('maya_checkout.deep_link_uri', 'negosyo://'),
            'negosyoPlayStore' => config('maya_checkout.negosyo_play_store'),
            'businessPlayStore' => config('maya_checkout.business_play_store'),
            'settlementUrl' => 'https://pbm.paymaya.com/settlements/reports',
            'billerAdminUrl' => route('epayplus.integrations.maya'),
            'checkoutDemo' => $request->boolean('checkout_demo'),
            'checkoutRef' => $request->query('ref'),
        ]);
    }

    public function createCheckout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->checkoutService->createCheckout($validated);

        if (! $result['success'] || empty($result['redirect_url'])) {
            return back()->with('error', $result['message'] ?? 'Could not create checkout session.');
        }

        return redirect()->away($result['redirect_url']);
    }
}
