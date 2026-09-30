<?php

declare(strict_types=1);

namespace App\Services\Currency;

/**
 * Konverter presisi antar mata uang — tanpa float.
 *
 * Konvensi kurs: string desimal "IDR per 1 major unit"
 * (mis. USD = "16500.00000000"). Jalur konversi selalu lewat IDR:
 *   major_tujuan = (major_asal * rate_asal) / rate_tujuan
 * lalu dibulatkan half-up ke minor-units tujuan memakai bcmath.
 */
final class CurrencyConverter
{
    private const SCALE = 12;

    /**
     * Konversi penuh memakai objek Money + kurs string + desimal tujuan.
     */
    public static function convert(
        Money $from,
        string $toCode,
        int $toDecimals,
        string $fromRate,
        string $toRate,
    ): Money {
        $minor = self::convertMinor(
            $from->minor(),
            $from->decimals(),
            $toDecimals,
            $fromRate,
            $toRate,
        );

        return Money::fromMinor($minor, $toCode, $toDecimals);
    }

    /**
     * Inti integer-safe: minor_asal (int) -> minor_tujuan (int).
     * Checkout boleh memanggil ini langsung agar tidak ada float
     * di seluruh rantai perhitungan.
     */
    public static function convertMinor(
        int $fromMinor,
        int $fromDecimals,
        int $toDecimals,
        string $fromRate,
        string $toRate,
    ): int {
        self::assertRate($fromRate);
        self::assertRate($toRate);

        if ($fromDecimals < 0 || $fromDecimals > 8 || $toDecimals < 0 || $toDecimals > 8) {
            throw new \InvalidArgumentException('decimal_places harus 0..8.');
        }

        $majorFrom = bcdiv((string) $fromMinor, self::pow10($fromDecimals), self::SCALE);
        $inIdr = bcmul($majorFrom, $fromRate, self::SCALE);
        $majorTo = bcdiv($inIdr, $toRate, self::SCALE);
        $scaledTo = bcmul($majorTo, self::pow10($toDecimals), self::SCALE);

        return self::roundHalfUpToInt($scaledTo);
    }

    /** Bulatkan string desimal ke int (half-up, menjauhi nol saat negatif). */
    public static function roundHalfUpToInt(string $decimal): int
    {
        $decimal = trim($decimal);
        if (! preg_match('/^-?\d+(\.\d+)?$/', $decimal)) {
            throw new \InvalidArgumentException('Nilai desimal tidak valid.');
        }

        $negative = str_starts_with($decimal, '-');
        $unsigned = $negative ? substr($decimal, 1) : $decimal;
        [$intPart, $fracPart] = array_pad(explode('.', $unsigned, 2), 2, '0');
        $fracPart = $fracPart ?? '0';

        $increment = (int) ($fracPart[0] ?? '0') >= 5;
        $intPart = ltrim($intPart, '0');
        $intPart = $intPart === '' ? '0' : $intPart;

        if ($increment) {
            $intPart = self::addOne($intPart);
        }

        // Guard overflow int di 32/64-bit: tetap dalam rentang PHP_INT.
        if (strlen(ltrim($intPart, '-')) > 18) {
            throw new \OverflowException('Hasil konversi melebihi rentang integer.');
        }

        $result = (int) $intPart;

        return $negative ? -$result : $result;
    }

    private static function assertRate(string $rate): void
    {
        if (! preg_match('/^\d+(\.\d+)?$/', trim($rate)) || bccomp(trim($rate), '0', self::SCALE) <= 0) {
            throw new \InvalidArgumentException('exchange_rate harus string desimal > 0.');
        }
    }

    private static function pow10(int $exp): string
    {
        return (string) (10 ** max(0, $exp));
    }

    private static function addOne(string $digits): string
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
