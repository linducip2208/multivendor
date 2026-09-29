<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Decimal-safe monetary arithmetic.
 *
 * Every amount is normalised to an integer of minor units (e.g. rupiah have no
 * minor unit, so 1 rupiah == 1 minor unit; prices with decimals such as USD use
 * 100 minor units per major unit). All operations are integer-only so that
 * repeated settlement/commission/refund math can never drift the way binary
 * floats do.
 */
final class Money
{
    public const SCALE_DEFAULT = 100;

    public const PRECISION_DEFAULT = 2;

    private function __construct(public readonly int $minor, public readonly int $scale) {}

    public static function of(float|int|string|null $amount, int $scale = self::SCALE_DEFAULT): self
    {
        if ($amount === null || $amount === '') {
            return new self(0, $scale);
        }
        if (is_int($amount)) {
            return new self($amount * $scale, $scale);
        }
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Money amount must be numeric.');
        }

        $normalised = self::normaliseDecimal((string) $amount);
        [$whole, $rawFraction] = array_pad(explode('.', $normalised, 2), 2, '');

        $fraction = substr(str_pad($rawFraction, self::PRECISION_DEFAULT, '0'), 0, self::PRECISION_DEFAULT);
        $minor = ((int) $whole * $scale) + (int) ($scale > 0 ? $fraction : 0);

        if (strlen($rawFraction) > self::PRECISION_DEFAULT && (int) $rawFraction[self::PRECISION_DEFAULT] >= 5) {
            $minor += 1;
        }

        return new self($minor, $scale);
    }

    public static function zero(int $scale = self::SCALE_DEFAULT): self
    {
        return new self(0, $scale);
    }

    public function add(self|float|int|string $other): self
    {
        return new self($this->minor + self::coerce($other, $this->scale), $this->scale);
    }

    public function subtract(self|float|int|string $other): self
    {
        return new self($this->minor - self::coerce($other, $this->scale), $this->scale);
    }

    public function multiply(float|int|string $factor): self
    {
        if (! is_numeric($factor)) {
            throw new InvalidArgumentException('Money factor must be numeric.');
        }

        // Half-up rounding on the integer product keeps commission math stable.
        $product = $this->minor * (float) $factor;
        $rounded = (int) (($product >= 0 ? floor($product + 0.5) : ceil($product - 0.5)));

        return new self($rounded, $this->scale);
    }

    /** Splits an amount into n parts that always sum back to the original amount. */
    public function allocate(int $parts): array
    {
        if ($parts < 1) {
            throw new InvalidArgumentException('Cannot allocate to fewer than one part.');
        }

        $base = intdiv($this->minor, $parts);
        $remainder = $this->minor - ($base * $parts);

        $result = [];
        for ($i = 0; $i < $parts; $i++) {
            $result[] = new self($base + ($i < abs($remainder) ? ($remainder < 0 ? -1 : 1) : 0), $this->scale);
        }

        return $result;
    }

    public function min(self|float|int|string $other): self
    {
        $value = self::coerce($other, $this->scale);

        return new self(min($this->minor, $value), $this->scale);
    }

    public function max(self|float|int|string $other): self
    {
        $value = self::coerce($other, $this->scale);

        return new self(max($this->minor, $value), $this->scale);
    }

    public function maxZero(): self
    {
        return new self(max(0, $this->minor), $this->scale);
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function compare(self|float|int|string $other): int
    {
        return $this->minor <=> self::coerce($other, $this->scale);
    }

    /** Returns a value rounded to the nearest 0.01 major unit, ready for DECIMAL(15,2). */
    public function toDecimal(): string
    {
        return number_format($this->major(), self::PRECISION_DEFAULT, '.', '');
    }

    public function major(): float
    {
        return $this->minor / $this->scale;
    }

    public function toFloat(): float
    {
        return $this->major();
    }

    private static function coerce(self|float|int|string $value, int $scale): int
    {
        return $value instanceof self ? $value->minor : self::of($value, $scale)->minor;
    }

    private static function normaliseDecimal(string $value): string
    {
        $value = str_replace([' ', ',', 'Rp', 'rp'], '', trim($value));

        if (str_starts_with($value, '-')) {
            $value = substr($value, 1);
        } elseif (str_starts_with($value, '+')) {
            $value = substr($value, 1);
        }

        if (! preg_match('/^\d*(\.\d*)?$/', $value) || $value === '' || $value === '.') {
            throw new InvalidArgumentException('Money amount must be numeric.');
        }

        return $value;
    }

    /** Sum of a list of scalars without floating point drift. */
    public static function sum(iterable $amounts, int $scale = self::SCALE_DEFAULT): self
    {
        $total = 0;
        foreach ($amounts as $amount) {
            $total += self::coerce($amount, $scale);
        }

        return new self($total, $scale);
    }
}
