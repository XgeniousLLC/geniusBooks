<?php

use App\Services\Invoicing\InvoiceCalculator;

function calc(): InvoiceCalculator
{
    return new InvoiceCalculator;
}

function line(array $overrides = []): array
{
    return array_merge([
        'quantity' => 1,
        'unit_price' => 0,
        'discount_type' => null,
        'discount_value' => null,
        'tax_rate' => 0,
    ], $overrides);
}

it('computes exclusive tax on a single line', function () {
    $totals = calc()->calculate(
        [line(['unit_price' => 2500, 'tax_rate' => 10])],
        null,
        false,
        'USD',
    );

    expect($totals['subtotal'])->toBe(2500)
        ->and($totals['discount'])->toBe(0)
        ->and($totals['tax'])->toBe(250)
        ->and($totals['total'])->toBe(2750);
});

it('sums multiple lines like the spec example', function () {
    $totals = calc()->calculate(
        [
            line(['unit_price' => 2500, 'tax_rate' => 10]),
            line(['unit_price' => 100, 'tax_rate' => 10]),
        ],
        null,
        false,
        'USD',
    );

    expect($totals['subtotal'])->toBe(2600)
        ->and($totals['tax'])->toBe(260)
        ->and($totals['total'])->toBe(2860);
});

it('applies percent and fixed line discounts', function () {
    $percent = calc()->calculate([line(['unit_price' => 1000, 'discount_type' => 'percent', 'discount_value' => 10])], null, false, 'USD');
    expect($percent['discount'])->toBe(100)->and($percent['total'])->toBe(900);

    $fixed = calc()->calculate([line(['unit_price' => 1000, 'discount_type' => 'fixed', 'discount_value' => '1.50'])], null, false, 'USD');
    expect($fixed['discount'])->toBe(150)->and($fixed['total'])->toBe(850);
});

it('applies an invoice-level discount before tax', function () {
    $totals = calc()->calculate(
        [
            line(['unit_price' => 2500, 'tax_rate' => 10]),
            line(['unit_price' => 100, 'tax_rate' => 10]),
        ],
        ['type' => 'percent', 'value' => 10],
        false,
        'USD',
    );

    expect($totals['subtotal'])->toBe(2600)
        ->and($totals['discount'])->toBe(260)
        ->and($totals['tax'])->toBe(234)
        ->and($totals['total'])->toBe(2574);
});

it('allocates invoice discounts so the parts sum exactly to the whole', function () {
    $totals = calc()->calculate(
        [
            line(['unit_price' => 1000]),
            line(['unit_price' => 1000]),
            line(['unit_price' => 1000]),
        ],
        ['type' => 'fixed', 'value' => '1.00'],
        false,
        'USD',
    );

    $allocated = array_sum(array_map(fn ($l) => $l['discount'], $totals['lines']));

    expect($allocated)->toBe(100)
        ->and($totals['total'])->toBe(2900);
});

it('extracts inclusive tax', function () {
    $totals = calc()->calculate([line(['unit_price' => 1100, 'tax_rate' => 10])], null, true, 'USD');

    expect($totals['tax'])->toBe(100)
        ->and($totals['total'])->toBe(1100);
});

it('clamps discounts to the base amount', function () {
    $totals = calc()->calculate([line(['unit_price' => 500, 'discount_type' => 'fixed', 'discount_value' => '99.00'])], null, false, 'USD');

    expect($totals['discount'])->toBe(500)
        ->and($totals['total'])->toBe(0);
});
