<?php

namespace App\Services\Invoicing;

use App\Support\Money;

/**
 * Authoritative invoice totals engine.
 *
 * Order of operations:
 *   1. Line subtotal = quantity x unit price (rounded half-up).
 *   2. Line discount (percent or fixed), clamped to the line subtotal.
 *   3. Invoice-level discount, allocated across lines in proportion to their
 *      post-line-discount net, with the remainder assigned to the last line.
 *   4. Tax is computed per line on the remaining base:
 *        - exclusive: base x rate
 *        - inclusive: base x rate / (100 + rate) (extracted)
 *   5. Total = (subtotal - discount) + tax for exclusive, or
 *              (subtotal - discount) for inclusive (tax already included).
 *
 * Money is handled in integer minor units; the UI is never trusted.
 */
class InvoiceCalculator
{
    /**
     * @param  list<array{quantity: int|float|string, unit_price: int, discount_type?: ?string, discount_value?: int|float|string|null, tax_rate?: int|float|string|null}>  $lines
     * @param  array{type?: ?string, value?: int|float|string|null}|null  $invoiceDiscount
     * @return array{subtotal: int, discount: int, tax: int, total: int, lines: list<array{subtotal: int, discount: int, tax: int, total: int}>}
     */
    public function calculate(array $lines, ?array $invoiceDiscount, bool $taxInclusive, string $currency): array
    {
        $gross = [];
        $lineDiscounts = [];
        $nets = [];
        $rates = [];

        foreach ($lines as $line) {
            $lineGross = Money::of((int) $line['unit_price'], $currency)
                ->multiply((float) ($line['quantity'] ?? 0));
            $discount = $this->discountAmount(
                $lineGross,
                $line['discount_type'] ?? null,
                $line['discount_value'] ?? null,
                $currency,
            );
            $net = $lineGross->subtract($discount);

            $gross[] = $lineGross->amount();
            $lineDiscounts[] = $discount->amount();
            $nets[] = $net->amount();
            $rates[] = (float) ($line['tax_rate'] ?? 0);
        }

        $subtotal = array_sum($gross);
        $lineDiscountTotal = array_sum($lineDiscounts);
        $netTotal = array_sum($nets);

        $invoiceDiscount = $this->discountAmount(
            Money::of($netTotal, $currency),
            $invoiceDiscount['type'] ?? null,
            $invoiceDiscount['value'] ?? null,
            $currency,
        )->amount();

        $shares = $this->allocate($nets, $invoiceDiscount);

        $resultLines = [];
        $taxTotal = 0;
        $total = 0;

        foreach ($lines as $index => $line) {
            $base = Money::of($nets[$index] - $shares[$index], $currency);
            $rate = $rates[$index];

            $tax = $taxInclusive
                ? $base->multiply($rate / (100 + $rate))
                : $base->percentage($rate);

            $lineTotal = $taxInclusive ? $base->amount() : $base->add($tax)->amount();

            $taxTotal += $tax->amount();
            $total += $lineTotal;

            $resultLines[] = [
                'subtotal' => $gross[$index],
                'discount' => $lineDiscounts[$index] + $shares[$index],
                'tax' => $tax->amount(),
                'total' => $lineTotal,
            ];
        }

        return [
            'subtotal' => $subtotal,
            'discount' => $lineDiscountTotal + $invoiceDiscount,
            'tax' => $taxTotal,
            'total' => $total,
            'lines' => $resultLines,
        ];
    }

    private function discountAmount(Money $base, ?string $type, int|float|string|null $value, string $currency): Money
    {
        if ($type === null || $type === '' || $value === null || (float) $value <= 0) {
            return Money::zero($currency);
        }

        $discount = match ($type) {
            'percent' => $base->percentage((float) $value),
            'fixed' => Money::fromDecimal((string) $value, $currency),
            default => Money::zero($currency),
        };

        if ($discount->amount() < 0) {
            return Money::zero($currency);
        }

        return $discount->amount() > $base->amount() ? $base : $discount;
    }

    /**
     * Allocate an amount across weights, assigning any rounding remainder to
     * the final entry so the parts sum exactly to the whole.
     *
     * @param  list<int>  $weights
     * @return list<int>
     */
    private function allocate(array $weights, int $amount): array
    {
        $count = count($weights);
        $total = array_sum($weights);

        if ($count === 0) {
            return [];
        }

        if ($amount === 0 || $total <= 0) {
            return array_fill(0, $count, 0);
        }

        $shares = [];
        $allocated = 0;
        $last = $count - 1;

        foreach ($weights as $index => $weight) {
            if ($index === $last) {
                $shares[$index] = $amount - $allocated;

                continue;
            }

            $share = (int) round($amount * $weight / $total, 0, PHP_ROUND_HALF_UP);
            $shares[$index] = $share;
            $allocated += $share;
        }

        return $shares;
    }
}
