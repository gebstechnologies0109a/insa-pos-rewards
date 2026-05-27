<?php

namespace Tests\Unit;

use App\Support\MayaBiller\MayaBillerResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MayaBillerResponseTest extends TestCase
{
    #[Test]
    public function success_without_fees_returns_result_only(): void
    {
        $response = MayaBillerResponse::success();

        $this->assertSame(
            ['result' => ['code' => '0000']],
            $response->getData(true)
        );
    }

    #[Test]
    public function success_with_fees_includes_fees_object(): void
    {
        $response = MayaBillerResponse::success([
            'convenienceFee' => 0,
            'serviceFee' => 5,
            'totalFee' => 5,
        ]);

        $this->assertSame([
            'result' => ['code' => '0000'],
            'fees' => [
                'convenienceFee' => 0,
                'serviceFee' => 5,
                'totalFee' => 5,
            ],
        ], $response->getData(true));
    }

    #[Test]
    public function error_includes_message_and_no_fees(): void
    {
        $response = MayaBillerResponse::error('2596', 'Amount is invalid');

        $this->assertSame([
            'result' => [
                'code' => '2596',
                'message' => 'Amount is invalid',
            ],
        ], $response->getData(true));
    }

    #[Test]
    public function it_rejects_unknown_result_codes(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MayaBillerResponse::error('4001', 'Not allowed');
    }
}
