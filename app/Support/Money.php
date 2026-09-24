<?php

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Immutable money value object.
 *
 * Amounts are stored as integer minor units (e.g. cents) to avoid
 * floating-point drift. Arithmetic rounds half-up to the currency scale.
 */
final class Money implements JsonSerializable
{
    /** Currencies with no minor unit, or a scale other than 2. */
    private const SCALES = [
        'JPY' => 0, 'KRW' => 0, 'VND' => 0, 'CLP' => 0, 'ISK' => 0,
        'BHD' => 3, 'KWD' => 3, 'OMR' => 3, 'TND' => 3,
        'CLF' => 4, 'UYW' => 4,
    ];

    private const SYMBOLS = [
        'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥', 'CNY' => '¥',
        'INR' => '₹', 'BDT' => '৳', 'AUD' => 'A$', 'CAD' => 'C$', 'CHF' => 'CHF',
        'SGD' => 'S$', 'HKD' => 'HK$', 'NZD' => 'NZ$', 'ZAR' => 'R', 'BRL' => 'R$',
        'MXN' => 'MX$', 'AED' => 'د.إ', 'SAR' => '﷼', 'TRY' => '₺', 'RUB' => '₽',
        'IDR' => 'Rp', 'MYR' => 'RM', 'PHP' => '₱', 'THB' => '฿', 'PKR' => '₨',
        'NGN' => '₦', 'KES' => 'KSh', 'EGP' => 'E£',
    ];

    /**
     * Optional per-request formatting override, e.g. a company's custom
     * symbol and position. Null means use the built-in defaults.
     *
     * @var array{currency: string, symbol: ?string, position: string}|null
     */
    private static ?array $formatting = null;

    public function __construct(
        private readonly int $amount,
        private string $currency = 'USD',
    ) {
        $currency = strtoupper($currency);

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException("Invalid currency code: {$currency}");
        }

        $this->currency = $currency;
    }

    public static function of(int $minorUnits, string $currency = 'USD'): self
    {
        return new self($minorUnits, $currency);
    }

    public static function zero(string $currency = 'USD'): self
    {
        return new self(0, $currency);
    }

    /**
     * Build from a major-unit value (string|int|float), rounding half-up.
     */
    public static function fromDecimal(int|float|string $major, string $currency = 'USD'): self
    {
        $currency = strtoupper($currency);
        $scale = self::scaleFor($currency);
        $factor = 10 ** $scale;

        return new self((int) round((float) $major * $factor, 0, PHP_ROUND_HALF_UP), $currency);
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function scale(): int
    {
        return self::scaleFor($this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function applySign(int $sign): self
    {
        return new self($sign < 0 ? -$this->amount : $this->amount, $this->currency);
    }

    public function negated(): self
    {
        return $this->applySign(-1);
    }

    public function absolute(): self
    {
        return new self(abs($this->amount), $this->currency);
    }

    /**
     * Multiply by a factor (int/float), rounding half-up.
     */
    public function multiply(int|float $factor): self
    {
        return new self((int) round($this->amount * $factor, 0, PHP_ROUND_HALF_UP), $this->currency);
    }

    /**
     * Percentage of this amount, e.g. 10.0 for 10%. Rounds half-up.
     */
    public function percentage(float $rate): self
    {
        return $this->multiply($rate / 100);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amount === $other->amount;
    }

    /**
     * Major-unit decimal string with the currency scale.
     */
    public function toDecimal(): string
    {
        $scale = $this->scale();
        $major = $this->amount / (10 ** $scale);

        return number_format($major, $scale, '.', '');
    }

    public function format(bool $withSymbol = true): string
    {
        $scale = $this->scale();
        $major = $this->amount / (10 ** $scale);
        $formatted = number_format(abs($major), $scale);

        if (! $withSymbol) {
            return $this->amount < 0 ? '-'.$formatted : $formatted;
        }

        $symbol = self::symbolFor($this->currency);
        $position = 'prefix';

        if (self::$formatting !== null && self::$formatting['currency'] === $this->currency) {
            if (self::$formatting['symbol'] !== null) {
                $symbol = self::$formatting['symbol'];
            }
            $position = self::$formatting['position'];
        }

        $body = $position === 'suffix' ? $formatted.' '.$symbol : $symbol.$formatted;

        return $this->amount < 0 ? '-'.$body : $body;
    }

    /**
     * Override how a currency is rendered (custom symbol and/or position).
     * Used per-company; pass null currency to reset to defaults.
     */
    public static function configureFormatting(?string $currency, ?string $symbol = null, ?string $position = 'prefix'): void
    {
        if ($currency === null) {
            self::$formatting = null;

            return;
        }

        self::$formatting = [
            'currency' => strtoupper($currency),
            'symbol' => ($symbol !== null && $symbol !== '') ? $symbol : null,
            'position' => $position === 'suffix' ? 'suffix' : 'prefix',
        ];
    }

    public static function scaleFor(string $currency): int
    {
        return self::SCALES[strtoupper($currency)] ?? 2;
    }

    public static function symbolFor(string $currency): string
    {
        $currency = strtoupper($currency);

        return self::SYMBOLS[$currency] ?? $currency;
    }

    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'formatted' => $this->format(),
        ];
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot combine {$this->currency} with {$other->currency}."
            );
        }
    }
}
