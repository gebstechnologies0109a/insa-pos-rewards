<?php

namespace Tests\Unit\POS;

use App\Services\POS\PosSaleTotalsResolver;
use PHPUnit\Framework\TestCase;

class PosSaleTotalsResolverTest extends TestCase
{
    private PosSaleTotalsResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PosSaleTotalsResolver;
    }

    public function test_computes_from_items_only(): void
    {
        $items = [
            ['qty' => 2, 'price' => 100, 'discount' => 10],
            ['qty' => 1, 'price' => 50, 'discount' => 0],
        ];

        $result = $this->resolver->resolve($items);

        $this->assertEquals(250, $result['subtotal']);
        $this->assertEquals(10, $result['item_discount_total']);
        $this->assertEquals(240, $result['total']);
    }

    public function test_authoritative_discount_total_includes_order_discount(): void
    {
        $items = [
            ['qty' => 10, 'price' => 1500, 'discount' => 0],
        ];

        $result = $this->resolver->resolve($items, [
            'subtotal'       => 15000,
            'discount_total' => 0,
            'order_discount' => 0,
            'total'          => 15000,
        ]);

        $this->assertEquals(15000, $result['total']);
        $this->assertEquals(15000, $result['subtotal']);
    }

    public function test_order_discount_without_discount_total_field(): void
    {
        $items = [
            ['qty' => 1, 'price' => 10000, 'discount' => 500],
            ['qty' => 1, 'price' => 5000, 'discount' => 0],
        ];

        $result = $this->resolver->resolve($items, [
            'order_discount' => 3968.14,
        ]);

        $this->assertEquals(15000, $result['subtotal']);
        $this->assertEquals(4468.14, $result['discount_total']);
        $this->assertEquals(10531.86, $result['total']);
    }

    public function test_client_total_used_when_internally_consistent(): void
    {
        $items = [
            ['qty' => 1, 'price' => 15000, 'discount' => 0],
        ];

        $result = $this->resolver->resolve($items, [
            'subtotal'       => 15000,
            'discount_total' => 3968.14,
            'total'          => 11031.86,
        ]);

        $this->assertEquals(11031.86, $result['total']);
    }

    public function test_register_total_wins_over_inconsistent_discount_fields(): void
    {
        $result = $this->resolver->resolve(
            [['qty' => 10, 'price' => 1500, 'discount' => 0]],
            [
                'subtotal'       => 15000,
                'discount_total' => 3968.14,
                'total'          => 15000,
            ]
        );

        $this->assertEquals(15000, $result['total']);
        $this->assertEquals(0, $result['discount_total']);
    }
}
