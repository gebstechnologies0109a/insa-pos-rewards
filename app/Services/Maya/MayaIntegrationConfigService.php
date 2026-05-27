<?php

namespace App\Services\Maya;

use App\Models\EPayPlus\EPaySetting;

class MayaIntegrationConfigService
{
    public function billerEnabled(): bool
    {
        return config('maya_biller.enabled')
            || filter_var(EPaySetting::getValue('maya_biller_enabled', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    public function checkoutEnabled(): bool
    {
        return (bool) config('maya_checkout.enabled')
            && $this->checkoutHasCredentials();
    }

    public function checkoutDemoMode(): bool
    {
        return (bool) config('maya_checkout.demo_mode') || ! $this->checkoutHasCredentials();
    }

    public function checkoutHasCredentials(): bool
    {
        $pk = config('maya_checkout.public_key');
        $sk = config('maya_checkout.secret_key');

        return filled($pk) && filled($sk)
            && ! str_contains((string) $pk, 'placeholder')
            && ! str_contains((string) $sk, 'placeholder');
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiPayload(): array
    {
        return [
            'biller_enabled' => $this->billerEnabled(),
            'checkout_enabled' => $this->checkoutEnabled(),
            'checkout_demo_mode' => $this->checkoutDemoMode(),
            'negosyo_package' => config('maya_checkout.negosyo_package', 'com.paymaya.negosyo'),
            'business_package' => config('maya_checkout.business_package', 'ph.maya.business.android'),
            'deep_link_uri' => config('maya_checkout.deep_link_uri', 'negosyo://'),
            'business_deep_link' => config('maya_checkout.business_deep_link', 'https://www.maya.ph/business/app/'),
            'feature_flags' => [
                'open_negosyo_app' => true,
                'open_business_app' => true,
                'partner_biller' => $this->billerEnabled(),
                'maya_checkout' => $this->checkoutEnabled() || $this->checkoutDemoMode(),
                'wallet_balances' => true,
                'transaction_history' => true,
                'eload' => true,
                'bills' => true,
                'cashin' => true,
            ],
            'admin_urls' => [
                'biller' => route('epayplus.integrations.maya'),
                'negosyo_hub' => route('epayplus.integrations.maya-negosyo'),
            ],
            'settlement_url' => config('maya_biller.settlement_portal_url'),
        ];
    }
}
