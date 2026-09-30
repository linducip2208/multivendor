<?php

declare(strict_types=1);

namespace App\Services\Currency;

/**
 * Value object uang dalam MINOR units (integer) — tanpa float.
 *
 * Contoh: IDR 150000 (decimals 0) = Rp150.000;
 * USD 909 (decimals 2) = $9.09.
 *
 * Semua operasi aritmetika memakai integer math sehingga aman
 * untuk checkout/payment (bebas galat biner float).
 */
final class Money
{
    private int $minor;

    private string $code;

    private int $decimals;

    private function __construct(int $minor, string $code, int $decimals)
    {
        if ($decimals < 0 || $decimals > 8) {
            throw new \InvalidArgumentException('decimal_places harus 0..8.');
        }
        $code = strtoupper(trim($code));
        if ($code === '' || strlen($code) > 3) {
            throw new \InvalidArgumentException('Kode mata uang tidak valid.');
        }

        $this->minor = $minor;
        $this->code = $code;
        $this->decimals = $decimals;
    }

    public static function fromMinor(int $minor, string $code, int $decimals): self
    {
        return new self($minor, $code, $decimals);
    }

    public static function zero(string $code, int $decimals): self
    {
        return new self(0, $code, $decimals);
    }

    /**
     * Bangun dari string major desimal ("1234.56", "-10.5", "150000").
     * Hanya parse string — TIDAK menerima float agar presisi terjaga.
     */
    public static function fromMajor(string $major, string $code, int $decimals): self
    {
        $major = trim($major);
        if (! preg_match('/^-?\d+(\.\d+)?$/', $major)) {
            throw new \InvalidArgumentException('Nominal major harus string desimal (contoh "1234.56").');
        }

        $negative = str_starts_with($major, '-');
        $unsigned = $negative ? substr($major, 1) : $major;
        [$intPart, $fracPart] = array_pad(explode('.', $unsigned, 2), 2, '');
        $fracPart = $fracPart ?? '';

        // Bulatkan half-up bila digit berlebih dibanding decimals.
        $minorString = ltrim($intPart, '0');
        $minorString = $minorString === '' ? '0' : $minorString;

        $kept = substr(str_pad($fracPart, $decimals, '0'), 0, $decimals);
        $extra = substr($fracPart, $decimals);

        $minorString .= str_pad($kept, $decimals, '0');

        if ($extra !== '' && $extra !== false && (int) $extra[0] >= 5) {
            $minorString = self::addOneToNumericString($minorString);
        }

        $minorString = ltrim($minorString, '0');
        $minorString = $minorString === '' ? '0' : $minorString;

        $minor = (int) $minorString;
        if ($negative) {
            $minor = -$minor;
        }

        return new self($minor, $code, $decimals);
    }

    public function minor(): int
    {
        return $this->minor;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function decimals(): int
    {
        return $this->decimals;
    }

    /** Representasi major sebagai string desimal (tanpa float). */
    public function toMajorString(): string
    {
        $negative = $this->minor < 0;
        $abs = (string) abs($this->minor);

        if ($this->decimals === 0) {
            return ($negative ? '-' : '').$abs;
        }

        $abs = str_pad($abs, $this->decimals + 1, '0', STR_PAD_LEFT);
        $intPart = substr($abs, 0, -$this->decimals);
        $fracPart = substr($abs, -$this->decimals);
        $intPart = ltrim($intPart, '0');
        $intPart = $intPart === '' ? '0' : $intPart;

        return ($negative ? '-' : '').$intPart.'.'.$fracPart;
    }

    public function isSameCurrency(self $other): bool
    {
        return $this->code === $other->code && $this->decimals === $other->decimals;
    }

    private function assertSameCurrency(self $other): void
    {
        if (! $this->isSameCurrency($other)) {
            throw new \DomainException('Operasi Money beda mata uang/desimal tidak diizinkan.');
        }
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->code, $this->decimals);
    }

    public function sub(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->code, $this->decimals);
    }

    /** Kali bilangan bulat (qty) — tetap integer math. */
    public function multiply(int $multiplier): self
    {
        return new self($this->minor * $multiplier, $this->code, $this->decimals);
    }

    public function equals(self $other): bool
    {
        return $this->isSameCurrency($other) && $this->minor === $other->minor;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor > $other->minor;
    }

    private static function addOneToNumericString(string $digits): string
    {
        $carry = 1;
        $out = '';
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $sum = ((int) $digits[$i]) + $carry;
            $out = ((string) ($sum % 10)).$out;
            $carry = intdiv($sum, 10);
            if ($carry === 0) {
                return substr($digits, 0, $i).$out;
            }
        }

        return '1'.$out;
    }
}
