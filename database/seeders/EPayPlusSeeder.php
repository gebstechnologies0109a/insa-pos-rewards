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
        $providers = array_merge(
            $this->eloadProviders(),
            $this->billsProviders(),
            $this->ecashProviders(),
            $this->rfidProviders(),
        );

        foreach ($providers as $i => $provider) {
            Provider::updateOrCreate(
                ['code' => $provider['code']],
                array_merge($provider, [
                    'logo_url' => $this->localProviderLogoPath($provider['code']),
                    'sort_order' => $i,
                    'is_active' => true,
                ])
            );
        }
    }

    /** @return list<array<string, mixed>> */
    private function eloadProviders(): array
    {
        $networks = [
            ['code' => 'GLOBE', 'name' => 'Globe', 'sms_number' => '2+1'],
            ['code' => 'SMART', 'name' => 'Smart', 'sms_number' => '2+1'],
            ['code' => 'TNT', 'name' => 'Talk N Text', 'sms_number' => '2+1'],
            ['code' => 'SUN', 'name' => 'Sun Cellular'],
            ['code' => 'TM', 'name' => 'TM'],
            ['code' => 'DITO', 'name' => 'DITO'],
            ['code' => 'GOMO', 'name' => 'GOMO'],
            ['code' => 'CIGNAL', 'name' => 'Cignal Prepaid'],
            ['code' => 'GSAT', 'name' => 'GSAT'],
            ['code' => 'SMARTBRO', 'name' => 'Smart Bro'],
            ['code' => 'CHERRYPREPAID', 'name' => 'Cherry Prepaid'],
            ['code' => 'GAMEPIN', 'name' => 'Game Pin'],
            ['code' => 'KURYENTELOAD', 'name' => 'Kuryente Load'],
        ];

        return array_map(fn ($n) => [
            'type' => 'ELOAD',
            'code' => $n['code'],
            'name' => $n['name'],
            'category' => 'Prepaid Load',
            'billing_type' => 'prepaid',
            'sms_number' => $n['sms_number'] ?? null,
        ], $networks);
    }

    /** @return list<array<string, mixed>> */
    private function billsProviders(): array
    {
        $groups = [
            'Telecommunications' => [
                ['code' => 'PLDT', 'name' => 'PLDT'],
                ['code' => 'SMART_BILL', 'name' => 'Smart Postpaid'],
                ['code' => 'GLOBE_BILL', 'name' => 'Globe Postpaid'],
                ['code' => 'SUN_BILL', 'name' => 'Sun Postpaid'],
                ['code' => 'DITO_BILL', 'name' => 'DITO Postpaid'],
                ['code' => 'INNOVE', 'name' => 'Innove (Globelines)'],
            ],
            'Electricity' => [
                ['code' => 'MERALCO', 'name' => 'Meralco'],
                ['code' => 'VECO', 'name' => 'VECO'],
                ['code' => 'MORE_POWER', 'name' => 'MORE Power'],
                ['code' => 'BOHOL_LIGHT', 'name' => 'Bohol Light'],
            ],
            'Water' => [
                ['code' => 'MAYNILAD', 'name' => 'Maynilad'],
                ['code' => 'MANILA_WATER', 'name' => 'Manila Water'],
                ['code' => 'MCWD', 'name' => 'Metro Cebu Water'],
                ['code' => 'PRIMEWATER', 'name' => 'Prime Water'],
            ],
            'Internet/Cable' => [
                ['code' => 'SKY', 'name' => 'Sky Cable'],
                ['code' => 'CONVERGE', 'name' => 'Converge ICT'],
                ['code' => 'CIGNAL_BILL', 'name' => 'Cignal TV'],
            ],
            'Government' => [
                ['code' => 'SSS', 'name' => 'SSS'],
                ['code' => 'PAGIBIG', 'name' => 'Pag-IBIG'],
                ['code' => 'PHILHEALTH', 'name' => 'PhilHealth'],
                ['code' => 'NBI', 'name' => 'NBI Clearance'],
            ],
            'Insurance' => [
                ['code' => 'SUNLIFE', 'name' => 'Sun Life'],
                ['code' => 'PRULIFE', 'name' => 'Pru Life UK'],
                ['code' => 'AXA', 'name' => 'AXA Philippines'],
            ],
            'Loans' => [
                ['code' => 'HOME_CREDIT', 'name' => 'Home Credit'],
                ['code' => 'BPI_LOAN', 'name' => 'BPI Loan'],
                ['code' => 'BDO_LOAN', 'name' => 'BDO Loan'],
                ['code' => 'CEBUANA', 'name' => 'Cebuana Lhuillier'],
            ],
            'Credit Cards' => [
                ['code' => 'BPI_CC', 'name' => 'BPI Credit Card'],
                ['code' => 'BDO_CC', 'name' => 'BDO Credit Card'],
                ['code' => 'METROBANK_CC', 'name' => 'Metrobank Credit Card'],
            ],
            'Real Estate' => [
                ['code' => 'CAMELLA', 'name' => 'Camella Homes'],
                ['code' => 'LUMINA', 'name' => 'Lumina Homes'],
            ],
        ];

        $providers = [];
        foreach ($groups as $category => $items) {
            foreach ($items as $item) {
                $providers[] = [
                    'type' => 'BILLS',
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'category' => $category,
                    'billing_type' => 'postpaid',
                ];
            }
        }

        return $providers;
    }

    /** @return list<array<string, mixed>> */
    private function ecashProviders(): array
    {
        $wallets = [
            ['code' => 'GCASH', 'name' => 'GCash'],
            ['code' => 'MAYA', 'name' => 'Maya'],
            ['code' => 'COINSPH', 'name' => 'Coins.ph'],
            ['code' => 'GRABPAY', 'name' => 'GrabPay'],
            ['code' => 'SHOPEEPAY', 'name' => 'ShopeePay'],
            ['code' => 'LAZADA', 'name' => 'Lazada Wallet'],
            ['code' => 'PALAWANPAY', 'name' => 'PalawanPay'],
            ['code' => 'DIBZ_PAY', 'name' => 'DIBZ Pay'],
            ['code' => 'MAYBANK', 'name' => 'Maybank'],
            ['code' => 'PDAX', 'name' => 'PDAX'],
            ['code' => 'HELLOMONEY', 'name' => 'HelloMoney'],
            ['code' => 'PRICELOCQ', 'name' => 'Pricelocq'],
            ['code' => 'DISKARTECH', 'name' => 'Diskartech'],
            ['code' => 'BUX', 'name' => 'Bux'],
            ['code' => 'NATIONLINK', 'name' => 'Nationlink'],
            ['code' => 'XENDIT', 'name' => 'Xendit'],
            ['code' => 'PERAHUB', 'name' => 'Perahub'],
            ['code' => 'ALLEASY', 'name' => 'AllEasy'],
            ['code' => 'JOJOPAY', 'name' => 'JojoPay'],
            ['code' => 'ECPAY_WALLET', 'name' => 'ECPay Wallet'],
            ['code' => 'MAXIM', 'name' => 'Maxim'],
            ['code' => 'ALING_PURING', 'name' => 'Aling Puring Credits'],
            ['code' => 'NETBANK', 'name' => 'Netbank'],
            ['code' => 'BIZMOTO', 'name' => 'Bizmoto'],
            ['code' => 'TOKTOKWALLET', 'name' => 'TokTok Wallet'],
            ['code' => 'ICASH', 'name' => 'iCash'],
            ['code' => 'REPAYPH', 'name' => 'RepayPH'],
            ['code' => 'VYBE', 'name' => 'Vybe'],
        ];

        return array_map(fn ($w) => [
            'type' => 'ECASH',
            'code' => $w['code'],
            'name' => $w['name'],
            'category' => 'E-Wallet',
            'billing_type' => 'prepaid',
        ], $wallets);
    }

    /** @return list<array<string, mixed>> */
    private function rfidProviders(): array
    {
        $items = [
            ['code' => 'EASYTRIP', 'name' => 'EasyTrip'],
            ['code' => 'AUTOSWEEP', 'name' => 'Autosweep'],
            ['code' => 'TAPNGO', 'name' => 'Tap&Go'],
            ['code' => 'CONNECT', 'name' => 'Connect RFID'],
            ['code' => 'ETC', 'name' => 'ETC RFID'],
            ['code' => 'CCLEX_RFID', 'name' => 'CCLEX RFID'],
            ['code' => 'RFID_ECARD', 'name' => 'RFID eCard'],
        ];

        return array_map(fn ($r) => [
            'type' => 'RFID',
            'code' => $r['code'],
            'name' => $r['name'],
            'category' => 'RFID Services',
            'billing_type' => 'prepaid',
        ], $items);
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
        $eloadCodes = ['GLOBE', 'SMART', 'TNT', 'SUN', 'TM', 'DITO', 'GOMO'];

        foreach ($eloadCodes as $network) {
            $provider = Provider::where('code', $network)->first();
            if (!$provider) {
                continue;
            }

            foreach ($eloadAmounts as $i => $amount) {
                $discount = $amount >= 100 ? $amount * 0.03 : $amount * 0.02;
                Product::updateOrCreate(
                    ['code' => "{$network}_{$amount}"],
                    [
                        'provider_id' => $provider->id,
                        'type' => 'ELOAD',
                        'billing_type' => 'prepaid',
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

        $billers = Provider::where('type', 'BILLS')->get();
        foreach ($billers as $biller) {
            Product::updateOrCreate(
                ['code' => "{$biller->code}_PAY"],
                [
                    'provider_id' => $biller->id,
                    'type' => 'BILLS',
                    'billing_type' => 'postpaid',
                    'name' => "{$biller->name} Payment",
                    'amount' => 0,
                    'retailer_price' => 0,
                    'fee' => 15,
                    'commission' => 5,
                    'description' => "Pay {$biller->name} bills",
                ]
            );
        }

        $wallets = Provider::where('type', 'ECASH')->get();
        foreach ($wallets as $wallet) {
            Product::updateOrCreate(
                ['code' => "{$wallet->code}_CASHIN"],
                [
                    'provider_id' => $wallet->id,
                    'type' => 'ECASH',
                    'billing_type' => 'prepaid',
                    'name' => "{$wallet->name} Cash-In",
                    'amount' => 0,
                    'retailer_price' => 0,
                    'fee' => 0,
                    'commission' => 0,
                    'description' => "{$wallet->name} wallet top-up",
                ]
            );
        }

        $rfidProviders = Provider::where('type', 'RFID')->get();
        foreach ($rfidProviders as $rfid) {
            Product::updateOrCreate(
                ['code' => "{$rfid->code}_RELOAD"],
                [
                    'provider_id' => $rfid->id,
                    'type' => 'RFID',
                    'billing_type' => 'prepaid',
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
