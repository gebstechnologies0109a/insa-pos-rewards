<?php

namespace Tests\Unit;

use App\Models\EPayPlus\Product;
use App\Models\EPayPlus\Provider;
use App\Services\MayaBiller\MayaBillerFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MayaBillerFeeServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_config_default_fees_when_no_product_or_override(): void
    {
        config([
            'maya_biller.fees.default' => [
                'convenience_fee' => 0,
                'service_fee' => 5,
            ],
            'maya_biller.fees.biller_overrides' => [],
        ]);

        $fees = (new MayaBillerFeeService)->compute('UNKNOWN_BILLER', 500);

        $this->assertSame([
            'convenienceFee' => 0.0,
            'serviceFee' => 5.0,
            'totalFee' => 5.0,
        ], $fees);
    }

    #[Test]
    public function it_uses_biller_override_from_config(): void
    {
        config([
            'maya_biller.fees.default' => [
                'convenience_fee' => 0,
                'service_fee' => 5,
            ],
            'maya_biller.fees.biller_overrides' => [
                'MERALCO' => [
                    'convenience_fee' => 2.5,
                    'service_fee' => 12.5,
                ],
            ],
        ]);

        $fees = (new MayaBillerFeeService)->compute('MERALCO', 1000);

        $this->assertSame([
            'convenienceFee' => 2.5,
            'serviceFee' => 12.5,
            'totalFee' => 15.0,
        ], $fees);
    }

    #[Test]
    public function it_uses_epay_product_fee_as_service_fee(): void
    {
        config([
            'maya_biller.fees.default' => [
                'convenience_fee' => 1,
                'service_fee' => 5,
            ],
            'maya_biller.fees.biller_overrides' => [],
        ]);

        $provider = Provider::create([
            'type' => 'BILLS',
            'code' => 'MERALCO',
            'name' => 'Meralco',
            'is_active' => true,
        ]);

        Product::create([
            'provider_id' => $provider->id,
            'type' => 'BILLS',
            'code' => 'MERALCO_PAY',
            'name' => 'Meralco Payment',
            'amount' => 0,
            'retailer_price' => 0,
            'fee' => 15,
            'commission' => 0,
            'is_active' => true,
        ]);

        $fees = (new MayaBillerFeeService)->compute('MERALCO', 500);

        $this->assertSame(1.0, $fees['convenienceFee']);
        $this->assertSame(15.0, $fees['serviceFee']);
        $this->assertSame(16.0, $fees['totalFee']);
    }
}
