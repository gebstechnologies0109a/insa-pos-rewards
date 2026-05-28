<?php

namespace App\Services\POS;

/**
 * Single source of truth for POS sale monetary totals.
 *
 * Line items use gross price × qty minus per-line discount. Order-level discount
 * is stored separately. Clients may send authoritative subtotal / discount_total /
 * total from the register; we validate against item math and prefer the client
 * totals when they are self-consistent (fixes sync omitting order_discount).
 */
class PosSaleTotalsResolver
{
    public const TOLERANCE = 0.02;

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $data
     * @return array{subtotal: float, item_discount_total: float, order_discount: float, discount_total: float, total: float}
     */
    public function resolve(array $items, array $data = []): array
    {
        $fromItems = $this->computeFromItems($items);

        $clientSubtotal = $this->optionalFloat($data, 'subtotal');
        $clientDiscountTotal = $this->optionalFloat($data, 'discount_total');
        $clientOrderDiscount = $this->optionalFloat($data, 'order_discount');
        $clientTotal = $this->optionalFloat($data, 'total');

        $subtotal = $clientSubtotal ?? $fromItems['subtotal'];

        // Register total is authoritative when explicitly provided (receipt / amount charged).
        if ($clientTotal !== null) {
            $total = round($clientTotal, 2);

            if ($clientDiscountTotal !== null
                && $this->clientTotalsAreInternallyConsistent($subtotal, $clientDiscountTotal, $total)) {
                $discountTotal = $clientDiscountTotal;
            } else {
                $discountTotal = round(max(0, $subtotal - $total), 2);
            }

            $orderDiscount = $clientOrderDiscount ?? max(0, round(
                $discountTotal - $fromItems['item_discount_total'],
                2
            ));
        } elseif ($clientDiscountTotal !== null) {
            $discountTotal = $clientDiscountTotal;
            $orderDiscount = $clientOrderDiscount ?? max(0, round(
                $discountTotal - $fromItems['item_discount_total'],
                2
            ));
            $total = round(max(0, $subtotal - $discountTotal), 2);
        } else {
            $orderDiscount = $clientOrderDiscount ?? 0.0;
            $discountTotal = round($fromItems['item_discount_total'] + $orderDiscount, 2);
            $total = round(max(0, $subtotal - $discountTotal), 2);
        }

        return [
            'subtotal'             => round($subtotal, 2),
            'item_discount_total'  => $fromItems['item_discount_total'],
            'order_discount'       => round($orderDiscount, 2),
            'discount_total'       => round($discountTotal, 2),
            'total'                => $total,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal: float, item_discount_total: float, line_total_sum: float}
     */
    public function computeFromItems(array $items): array
    {
        $subtotal = 0.0;
        $itemDiscountTotal = 0.0;
        $lineTotalSum = 0.0;

        foreach ($items as $item) {
            $qty = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            $lineDiscount = (float) ($item['discount'] ?? 0);
            $lineSubtotal = $qty * $price;
            $lineTotal = $lineSubtotal - $lineDiscount;

            $subtotal += $lineSubtotal;
            $itemDiscountTotal += $lineDiscount;
            $lineTotalSum += $lineTotal;
        }

        return [
            'subtotal'            => round($subtotal, 2),
            'item_discount_total' => round($itemDiscountTotal, 2),
            'line_total_sum'      => round($lineTotalSum, 2),
        ];
    }

    /**
     * @param  \App\Models\POS\PosSale  $sale
     * @return array{consistent: bool, expected_total: float, line_total_sum: float, messages: array<int, string>}
     */
    public function checkSale($sale): array
    {
        $sale->loadMissing('items');

        $items = $sale->items->map(fn ($i) => [
            'qty'      => $i->qty,
            'price'    => $i->price,
            'discount' => $i->discount,
        ])->all();

        $fromItems = $this->computeFromItems($items);
        $lineTotalSum = (float) $sale->items->sum('line_total');
        $expectedTotal = round(max(0, (float) $sale->subtotal - (float) $sale->discount_total), 2);
        $messages = [];

        if (abs($lineTotalSum - $fromItems['line_total_sum']) > self::TOLERANCE) {
            $messages[] = "Sum of line_total ({$lineTotalSum}) ≠ recomputed lines ({$fromItems['line_total_sum']}).";
        }

        if (abs((float) $sale->total - $expectedTotal) > self::TOLERANCE) {
            $messages[] = "pos_sales.total ({$sale->total}) ≠ subtotal − discount_total ({$expectedTotal}).";
        }

        if (abs($lineTotalSum - (float) $sale->total) > self::TOLERANCE) {
            $messages[] = "Sum of line_total ({$lineTotalSum}) ≠ pos_sales.total ({$sale->total}).";
        }

        return [
            'consistent'      => $messages === [],
            'expected_total'  => $expectedTotal,
            'line_total_sum'  => $lineTotalSum,
            'messages'        => $messages,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function optionalFloat(array $data, string $key): ?float
    {
        if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            return null;
        }

        return (float) $data[$key];
    }

    protected function clientTotalsAreInternallyConsistent(float $subtotal, float $discountTotal, float $total): bool
    {
        return abs($subtotal - $discountTotal - $total) <= self::TOLERANCE;
    }
}
