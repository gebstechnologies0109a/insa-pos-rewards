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
        $this->syncRetailerDualWallets();
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
            $this->mayaNegosyoSupplementProviders(),
        );

        $seen = [];
        $providers = array_values(array_filter($providers, function (array $provider) use (&$seen) {
            $code = strtoupper($provider['code']);
            if (isset($seen[$code])) {
                return false;
            }
            $seen[$code] = true;

            return true;
        }));

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

    /**
     * Maya Negosyo (com.paymaya.negosyo) + DaFox /portal promos alignment.
     * Billers are API-driven in Negosyo; APK only embeds ABSCBNMOB asset slug.
     *
     * @return list<array<string, mixed>>
     */
    private function mayaNegosyoSupplementProviders(): array
    {
        $groups = [
            'Telecommunications' => [
                ['code' => 'BAYANTEL', 'name' => 'Bayantel'],
            ],
            'Electricity' => [
                ['code' => 'DAVAOLIGHT', 'name' => 'Davao Light'],
                ['code' => 'BENECO', 'name' => 'BENECO'],
                ['code' => 'CEPALCO', 'name' => 'CEPALCO'],
                ['code' => 'ANGELES_ELECTRIC', 'name' => 'Angeles Electric'],
                ['code' => 'PENELCO', 'name' => 'PENELCO'],
                ['code' => 'DANECO', 'name' => 'DANECO'],
                ['code' => 'CEBECO1', 'name' => 'CEBECO I'],
                ['code' => 'CEBECO2', 'name' => 'CEBECO II'],
                ['code' => 'CEBECO3', 'name' => 'CEBECO III'],
                ['code' => 'PELCO1', 'name' => 'PELCO I'],
                ['code' => 'PELCO2', 'name' => 'PELCO II'],
                ['code' => 'SFELAPCO', 'name' => 'San Fernando Light'],
                ['code' => 'FLECO', 'name' => 'FLECO'],
                ['code' => 'NEECO1', 'name' => 'NEECO I'],
                ['code' => 'NEECO2_AREA1', 'name' => 'NEECO II Area 1'],
                ['code' => 'QUEZELCO1', 'name' => 'QUEZELCO I'],
                ['code' => 'QUEZELCO2', 'name' => 'QUEZELCO II'],
                ['code' => 'DECORP', 'name' => 'Dagupan Electric (DECORP)'],
                ['code' => 'ZAMCELCO', 'name' => 'ZAMCELCO'],
            ],
            'Water' => [
                ['code' => 'LAGUNAWATER', 'name' => 'Laguna Water'],
                ['code' => 'BORACAYWATER', 'name' => 'Boracay Water'],
                ['code' => 'CLARKWATER', 'name' => 'Clark Water'],
                ['code' => 'LAGUNA_WATER_DISTRICT', 'name' => 'Laguna Water District'],
                ['code' => 'BP_WATERWORKS', 'name' => 'BP Waterworks'],
                ['code' => 'STA_LUCIA_WATER', 'name' => 'Sta. Lucia Water'],
            ],
            'Internet/Cable' => [
                ['code' => 'ABSCBNMOB', 'name' => 'ABS-CBN Mobile'],
                ['code' => 'STREAMTECH', 'name' => 'Streamtech (Planet Cable)'],
                ['code' => 'CABLELINK', 'name' => 'Cablelink'],
                ['code' => 'GALAXY_CABLE', 'name' => 'Galaxy Cable'],
                ['code' => 'NOW_CORP', 'name' => 'NOW Corporation'],
                ['code' => 'PARASAT', 'name' => 'Parasat Cable'],
            ],
            'Government' => [
                ['code' => 'DFA', 'name' => 'DFA'],
                ['code' => 'LTO', 'name' => 'LTO'],
                ['code' => 'PSA', 'name' => 'PSA Serbilis'],
                ['code' => 'BIR', 'name' => 'BIR'],
                ['code' => 'LTFRB', 'name' => 'LTFRB'],
                ['code' => 'MARINA', 'name' => 'MARINA'],
                ['code' => 'PEZA', 'name' => 'PEZA'],
                ['code' => 'TIEZA', 'name' => 'TIEZA'],
                ['code' => 'MYEG', 'name' => 'MyEG Philippines'],
            ],
            'Insurance' => [
                ['code' => 'INSULAR_LIFE', 'name' => 'Insular Life'],
                ['code' => 'GENERALI', 'name' => 'Generali Philippines'],
                ['code' => 'COCOLIFE', 'name' => 'Cocolife'],
                ['code' => 'PARAMOUNT', 'name' => 'Paramount Life'],
                ['code' => 'STANDARD_INSURANCE', 'name' => 'Standard Insurance'],
                ['code' => 'PHILLIFE', 'name' => 'Philippine Life Financial'],
            ],
            'Loans' => [
                ['code' => 'TONIK', 'name' => 'Tonik Digital Bank'],
                ['code' => 'CASHALO', 'name' => 'Cashalo'],
                ['code' => 'AEON', 'name' => 'AEON Credit Service'],
                ['code' => 'TALA', 'name' => 'Tala'],
                ['code' => 'UNIONDIGITAL', 'name' => 'UnionDigital Bank'],
                ['code' => 'SKYPAY_LOAN', 'name' => 'SkyPay Loans'],
                ['code' => 'SB_FINANCE', 'name' => 'SB Finance'],
                ['code' => 'CHINATRUST_LOAN', 'name' => 'Chinabank (CTBC) Loan'],
                ['code' => 'GLOBAL_DOMINION', 'name' => 'Global Dominion Financing'],
                ['code' => 'ASIALINK', 'name' => 'Asialink'],
            ],
            'Credit Cards' => [
                ['code' => 'CHINABANK_CC', 'name' => 'China Bank Credit Card'],
                ['code' => 'AUB_CC', 'name' => 'AUB Credit Card'],
                ['code' => 'SECURITYBANK_CC', 'name' => 'Security Bank Mastercard'],
                ['code' => 'UNIONBANK_CC', 'name' => 'UnionBank Credit Card'],
                ['code' => 'ROBINSONSBANK_CC', 'name' => 'Robinsons Bank Credit Card'],
                ['code' => 'BOC_CC', 'name' => 'Bank of Commerce Credit Card'],
            ],
            'Transportation' => [
                ['code' => 'BEEP', 'name' => 'Beep Card'],
            ],
            'Travel' => [
                ['code' => 'PAL', 'name' => 'Philippine Airlines'],
                ['code' => 'CEBUPACIFIC', 'name' => 'Cebu Pacific'],
                ['code' => 'AIRASIA', 'name' => 'AirAsia'],
            ],
            'Payment Services' => [
                ['code' => 'DRAGONPAY', 'name' => 'Dragonpay'],
                ['code' => 'PESOPAY', 'name' => 'PesoPay'],
                ['code' => 'MULTIPAY', 'name' => 'Multipay'],
            ],
            'Education' => [
                ['code' => 'PHINMA_EDUCATION', 'name' => 'Phinma Education'],
                ['code' => 'MAPUA', 'name' => 'Mapua University'],
            ],
            'Real Estate' => [
                ['code' => 'BRIA_HOMES', 'name' => 'Bria Homes'],
                ['code' => 'AVIDA', 'name' => 'Avida Land'],
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

        $providers[] = [
            'type' => 'ECASH',
            'code' => 'GCASH_PERA_OUTLET',
            'name' => 'GCash Pera Outlet',
            'category' => 'E-Wallet',
            'billing_type' => 'prepaid',
        ];

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

    private function seedEloadProducts(): void
    {
        $eloadAmounts = [10, 20, 30, 50, 100, 150, 200, 300, 500, 1000];
        $eloadCodes = [
            'GLOBE', 'SMART', 'TNT', 'SUN', 'TM', 'DITO', 'GOMO',
            'CIGNAL', 'GSAT', 'SMARTBRO', 'CHERRYPREPAID', 'GAMEPIN', 'KURYENTELOAD',
        ];

        $obsoleteDenomCodes = [];
        foreach ($eloadCodes as $network) {
            foreach ([5, 15, 25] as $legacyAmount) {
                $obsoleteDenomCodes[] = "{$network}_{$legacyAmount}";
            }
        }

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
                        'product_kind' => 'regular',
                        'name' => "{$provider->name} {$amount}",
                        'amount' => $amount,
                        'retailer_price' => $amount - $discount,
                        'commission' => $discount,
                        'fee' => 0,
                        'description' => "{$provider->name} prepaid load ₱{$amount}",
                        'sort_order' => $i,
                        'is_active' => true,
                    ]
                );
            }
        }

        /** @var array<string, list<array{0: string, 1: string, 2: float, 3?: int, 4?: string}>> $promoCatalog */
        $promoCatalog = require database_path('data/eload_promos.php');
        $promoSortBase = 100;

        foreach ($promoCatalog as $network => $promos) {
            $provider = Provider::where('code', $network)->first();
            if (!$provider) {
                continue;
            }

            foreach ($promos as $i => $promo) {
                [$slug, $name, $amount] = $promo;
                $validityDays = $promo[3] ?? null;
                $description = $promo[4] ?? "{$name} promo";
                $discount = $amount >= 100 ? $amount * 0.03 : $amount * 0.02;

                Product::updateOrCreate(
                    ['code' => "{$network}_PROMO_{$slug}"],
                    [
                        'provider_id' => $provider->id,
                        'type' => 'ELOAD',
                        'billing_type' => 'prepaid',
                        'product_kind' => 'promo',
                        'name' => $name,
                        'amount' => $amount,
                        'retailer_price' => $amount - $discount,
                        'commission' => $discount,
                        'fee' => 0,
                        'description' => $description,
                        'validity_days' => $validityDays,
                        'sort_order' => $promoSortBase + $i,
                        'is_active' => true,
                    ]
                );
            }
        }

        Product::where('type', 'ELOAD')
            ->whereIn('code', $obsoleteDenomCodes)
            ->update(['is_active' => false]);
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
        $this->seedEloadProducts();

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

        $ecashTiers = [100, 200, 500, 1000, 2000, 5000];
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
                    'description' => "{$wallet->name} wallet top-up (any amount)",
                    'sort_order' => 0,
                ]
            );

            foreach ($ecashTiers as $i => $amount) {
                Product::updateOrCreate(
                    ['code' => "{$wallet->code}_CASHIN_{$amount}"],
                    [
                        'provider_id' => $wallet->id,
                        'type' => 'ECASH',
                        'billing_type' => 'prepaid',
                        'name' => "{$wallet->name} Cash-In ₱{$amount}",
                        'amount' => $amount,
                        'retailer_price' => $amount,
                        'fee' => 0,
                        'commission' => 0,
                        'description' => "{$wallet->name} minimum cash-in ₱{$amount}",
                        'sort_order' => $i + 1,
                    ]
                );
            }
        }

        $rfidAmounts = [100, 200, 500, 1000];
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
                    'description' => "{$rfid->name} RFID wallet reload (any amount)",
                    'sort_order' => 0,
                ]
            );

            foreach ($rfidAmounts as $i => $amount) {
                Product::updateOrCreate(
                    ['code' => "{$rfid->code}_RELOAD_{$amount}"],
                    [
                        'provider_id' => $rfid->id,
                        'type' => 'RFID',
                        'billing_type' => 'prepaid',
                        'name' => "{$rfid->name} Reload ₱{$amount}",
                        'amount' => $amount,
                        'retailer_price' => $amount,
                        'fee' => 0,
                        'commission' => 0,
                        'description' => "{$rfid->name} RFID reload ₱{$amount}",
                        'sort_order' => $i + 1,
                    ]
                );
            }
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
                'eload_balance' => 7000.00,
                'bills_balance' => 3000.00,
                'pin' => Hash::make('1234'),
                'is_active' => true,
            ]
        );
    }

    /**
     * 70% E-Load / 30% Bills from combined balance for retailers not yet split.
     * Skips EPDEMO001 and any retailer that already has bills_balance > 0.
     */
    private function syncRetailerDualWallets(): void
    {
        Retailer::syncAllDualWallets();
    }
}
