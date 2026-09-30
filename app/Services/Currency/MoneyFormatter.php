<?php

declare(strict_types=1);

namespace App\Services\Currency;

/**
 * Format tampilan locale-aware — tanpa float.
 *
 * - id-ID: ribuan ".", desimal ","  (Rp150.000 / $9,09)
 * - en-US: ribuan ",", desimal "."  (Rp150,000 / $9.09)
 * Locale lain fallback ke gaya en-US.
 * Pengelompokan dibangun manual dari string agar aman untuk nominal besar.
 */
final class MoneyFormatter
{
    public static function format(Money $money, string $locale = 'id-ID', ?string $symbol = null): string
    {
        $symbol = $symbol ?? $money->code().' ';
        [$thousand, $decimal] = self::separators($locale);

        $negative = $money->minor() < 0;
        $abs = (string) abs($money->minor());
        $decimals = $money->decimals();

        if ($decimals === 0) {
            $grouped = self::group($abs, $thousand);

            return ($negative ? '-' : '').$symbol.$grouped;
        }

        $abs = str_pad($abs, $decimals + 1, '0', STR_PAD_LEFT);
        $intPart = substr($abs, 0, -$decimals);
        $fracPart = substr($abs, -$decimals);

        return ($negative ? '-' : '').$symbol.self::group($intPart, $thousand).$decimal.$fracPart;
    }

    /** Format dari string major (tanpa float) bila belum ada objek Money. */
    public static function formatMajor(
        string $major,
        string $code,
        int $decimals,
        string $locale = 'id-ID',
        ?string $symbol = null,
    ): string {
        return self::format(Money::fromMajor($major, $code, $decimals), $locale, $symbol);
    }

    /** @return array{0: string, 1: string} [thousand, decimal] */
    public static function separators(string $locale): array
    {
        $normalized = strtolower(str_replace('_', '-', trim($locale)));

        if (str_starts_with($normalized, 'id')) {
            return ['.', ','];
        }

        return [',', '.'];
    }

    private static function group(string $digits, string $thousand): string
    {
        $digits = ltrim($digits, '0');
        $digits = $digits === '' ? '0' : $digits;

        $len = strlen($digits);
        if ($len <= 3) {
            return $digits;
        }

        $out = '';
        $count = 0;
        for ($i = $len - 1; $i >= 0; $i--) {
            $out = $digits[$i].$out;
            $count++;
            if ($count % 3 === 0 && $i !== 0) {
                $out = $thousand.$out;
            }
        }

        return $out;
    }
}
