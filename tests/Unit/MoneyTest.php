<?php

use App\Support\Money;

afterEach(function () {
    Money::configureFormatting(null);
});

it('stores amounts as integer minor units', function () {
    $money = Money::of(1000, 'USD');

    expect($money->amount())->toBe(1000)
        ->and($money->currency())->toBe('USD')
        ->and($money->scale())->toBe(2);
});

it('builds from decimal major units with half-up rounding', function () {
    expect(Money::fromDecimal('10.004')->amount())->toBe(1000)
        ->and(Money::fromDecimal('10.005')->amount())->toBe(1001)
        ->and(Money::fromDecimal('2500', 'USD')->amount())->toBe(250000);
});

it('adds and subtracts same-currency amounts', function () {
    $total = Money::of(2500)->add(Money::of(1500));

    expect($total->amount())->toBe(4000)
        ->and($total->subtract(Money::of(1000))->amount())->toBe(3000);
});

it('multiplies and applies percentages with rounding', function () {
    expect(Money::of(2500)->multiply(2)->amount())->toBe(5000)
        ->and(Money::of(2500)->percentage(10.0)->amount())->toBe(250)
        ->and(Money::of(999)->percentage(10.0)->amount())->toBe(100);
});

it('formats using currency symbol and scale', function () {
    expect(Money::of(125000)->format())->toBe('$1,250.00')
        ->and(Money::of(1000, 'JPY')->format())->toBe('¥1,000')
        ->and(Money::of(1234, 'EUR')->format())->toBe('€12.34')
        ->and(Money::of(125000)->format(false))->toBe('1,250.00');
});

it('refuses to combine different currencies', function () {
    Money::of(100, 'USD')->add(Money::of(100, 'EUR'));
})->throws(InvalidArgumentException::class);

it('rejects invalid currency codes', function () {
    new Money(100, 'US');
})->throws(InvalidArgumentException::class);

it('honours a configured symbol and suffix position', function () {
    Money::configureFormatting('USD', 'US$', 'suffix');

    expect(Money::of(125000)->format())->toBe('1,250.00 US$');
});

it('honours a custom prefix symbol', function () {
    Money::configureFormatting('USD', '¤', 'prefix');

    expect(Money::of(1000)->format())->toBe('¤10.00')
        ->and(Money::of(-500)->format())->toBe('-¤5.00');
});
