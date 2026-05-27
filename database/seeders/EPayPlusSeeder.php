<?php

namespace Database\Seeders;

use App\Models\EPayPlus\EPaySetting;
use App\Models\EPayPlus\Provider;
use App\Models\EPayPlus\Product;
use App\Models\EPayPlus\Retailer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EPayPlusSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSettings();
        $this->seedProviders();
        $this->seedProducts();
        $this->seedDemoRetailer();
    }

    private function seedSettings(): void
    {
        EPaySetting::updateOrCreate(
            ['key' => 'maya_biller_enabled'],
            ['value' => 'false']
        );
    }

    private function seedProviders(): void
    {
        $providers = [
            // E-Load
            ['type' => 'ELOAD', 'code' => 'GLOBE', 'name' => 'Globe', 'category' => 'Telecom', 'sms_number' => '2+1'],
            ['type' => 'ELOAD', 'code' => 'SMART', 'name' => 'Smart', 'category' => 'Telecom', 'sms_number' => '2+1'],
            ['type' => 'ELOAD', 'code' => 'TNT', 'name' => 'Talk N Text', 'category' => 'Telecom', 'sms_number' => '2+1'],
            ['type' => 'ELOAD', 'code' => 'DITO', 'name' => 'DITO', 'category' => 'Telecom'],
            ['type' => 'ELOAD', 'code' => 'TM', 'name' => 'TM', 'category' => 'Telecom'],
            ['type' => 'ELOAD', 'code' => 'GOMO', 'name' => 'GOMO', 'category' => 'Telecom'],

            // Bills
            ['type' => 'BILLS', 'code' => 'MERALCO', 'name' => 'Meralco', 'category' => 'Electricity'],
            ['type' => 'BILLS', 'code' => 'MAYNILAD', 'name' => 'Maynilad', 'category' => 'Water'],
            ['type' => 'BILLS', 'code' => 'PLDT', 'name' => 'PLDT', 'category' => 'Telecom'],
            ['type' => 'BILLS', 'code' => 'GLOBE_BILL', 'name' => 'Globe Telecom', 'category' => 'Telecom'],
            ['type' => 'BILLS', 'code' => 'SKY', 'name' => 'Sky Cable', 'category' => 'Cable'],
            ['type' => 'BILLS', 'code' => 'SSS', 'name' => 'SSS', 'category' => 'Government'],
            ['type' => 'BILLS', 'code' => 'PAGIBIG', 'name' => 'Pag-IBIG', 'category' => 'Government'],
            ['type' => 'BILLS', 'code' => 'PHILHEALTH', 'name' => 'PhilHealth', 'category' => 'Government'],
            ['type' => 'BILLS', 'code' => 'NBI', 'name' => 'NBI Clearance', 'category' => 'Government'],

            // E-Cash
            ['type' => 'ECASH', 'code' => 'GCASH', 'name' => 'GCash', 'category' => 'E-Wallet'],
            ['type' => 'ECASH', 'code' => 'MAYA', 'name' => 'Maya', 'category' => 'E-Wallet'],
            ['type' => 'ECASH', 'code' => 'COINS', 'name' => 'Coins.ph', 'category' => 'E-Wallet'],
            ['type' => 'ECASH', 'code' => 'GRABPAY', 'name' => 'GrabPay', 'category' => 'E-Wallet'],
            ['type' => 'ECASH', 'code' => 'SHOPEEPAY', 'name' => 'ShopeePay', 'category' => 'E-Wallet'],

            // RFID / Toll
            ['type' => 'RFID', 'code' => 'EASYTRIP', 'name' => 'EasyTrip', 'category' => 'RFID Services'],
            ['type' => 'RFID', 'code' => 'AUTOSWEEP', 'name' => 'Autosweep', 'category' => 'RFID Services'],
            ['type' => 'RFID', 'code' => 'TAPNGO', 'name' => 'Tap&Go', 'category' => 'RFID Services'],
            ['type' => 'RFID', 'code' => 'CONNECT', 'name' => 'Connect RFID', 'category' => 'RFID Services'],
            ['type' => 'RFID', 'code' => 'ETC', 'name' => 'ETC RFID', 'category' => 'RFID Services'],
            ['type' => 'RFID', 'code' => 'OTHER', 'name' => 'Other Toll RFID', 'category' => 'RFID Services'],
        ];

        foreach ($providers as $i => $provider) {
            Provider::updateOrCreate(
                ['code' => $provider['code']],
                array_merge($provider, [
                    'logo_url' => $this->localProviderLogoPath($provider['code']),
                    'sort_order' => $i,
                ])
            );
        }
    }

    private function localProviderLogoPath(string $code): ?string
    {
        $slug = provider_code_to_slug($code);
        foreach (['webp', 'png'] as $ext) {
            $relative = "images/providers/ic_provider_{$slug}.{$ext}";
            if (file_exists(public_path($relative))) {
                return '/' . $relative;
            }
        }

        return null;
    }

    private function seedProducts(): void
    {
        $eloadAmounts = [10, 15, 20, 25, 30, 50, 100, 150, 200, 300, 500, 1000];
        $networks = ['GLOBE', 'SMART', 'TNT', 'DITO', 'TM'];

        foreach ($networks as $network) {
            $provider = Provider::where('code', $network)->first();
            if (!$provider) continue;

            foreach ($eloadAmounts as $i => $amount) {
                $discount = $amount >= 100 ? $amount * 0.03 : $amount * 0.02;
                Product::updateOrCreate(
                    ['code' => "{$network}_{$amount}"],
                    [
                        'provider_id' => $provider->id,
                        'type' => 'ELOAD',
                        'name' => "{$provider->name} {$amount}",
                        'amount' => $amount,
                        'retailer_price' => $amount - $discount,
                        'commission' => $discount,
                        'fee' => 0,
                        'description' => "{$provider->name} prepaid load ₱{$amount}",
                        'sort_order' => $i,
                    ]
                );
            }
        }

        // Bills Payment products
        $billers = Provider::where('type', 'BILLS')->get();
        foreach ($billers as $biller) {
            Product::updateOrCreate(
                ['code' => "{$biller->code}_PAY"],
                [
                    'provider_id' => $biller->id,
                    'type' => 'BILLS',
                    'name' => "{$biller->name} Payment",
                    'amount' => 0,
                    'retailer_price' => 0,
                    'fee' => 15,
                    'commission' => 5,
                    'description' => "Pay {$biller->name} bills",
                ]
            );
        }

        // E-Cash products
        $wallets = Provider::where('type', 'ECASH')->get();
        foreach ($wallets as $wallet) {
            Product::updateOrCreate(
                ['code' => "{$wallet->code}_CASHIN"],
                [
                    'provider_id' => $wallet->id,
                    'type' => 'ECASH',
                    'name' => "{$wallet->name} Cash-In",
                    'amount' => 0,
                    'retailer_price' => 0,
                    'fee' => 0,
                    'commission' => 0,
                    'description' => "{$wallet->name} wallet top-up",
                ]
            );
        }

        // RFID reload products
        $rfidProviders = Provider::where('type', 'RFID')->get();
        foreach ($rfidProviders as $rfid) {
            Product::updateOrCreate(
                ['code' => "{$rfid->code}_RELOAD"],
                [
                    'provider_id' => $rfid->id,
                    'type' => 'RFID',
                    'name' => "{$rfid->name} Reload",
                    'amount' => 0,
                    'retailer_price' => 0,
                    'fee' => 0,
                    'commission' => 0,
                    'description' => "{$rfid->name} RFID wallet reload",
                ]
            );
        }
    }

    private function seedDemoRetailer(): void
    {
        Retailer::updateOrCreate(
            ['account_id' => 'EPDEMO001'],
            [
                'business_name' => 'Demo ePayPlus Store',
                'owner_name' => 'Admin',
                'mobile_number' => '09171234567',
                'email' => 'demo@epayplus.ph',
                'address' => 'Manila, Philippines',
                'balance' => 10000.00,
                'pin' => Hash::make('1234'),
                'is_active' => true,
            ]
        );
    }
}
