<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SystemSetting;

/**
 * Tenant-aware currency formatting.
 *
 * The storefront must never hardcode "Rp": a white-label tenant can change the
 * code, symbol, symbol position and decimal precision from the admin panel.
 * Values are resolved once per request and memoised.
 */
final class Currency
{
    private static ?array $config = null;

    /** @return array{code:string, symbol:string, position:string, decimals:int, thousands:string, decimal:string} */
    public static function config(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $decimals = (int) (SystemSetting::get('currency_decimals', '0') ?? 0);

        return self::$config = [
            'code' => (string) (SystemSetting::get('currency_code', 'IDR') ?? 'IDR'),
            'symbol' => (string) (SystemSetting::get('currency_symbol', 'Rp') ?? 'Rp'),
            'position' => (string) (SystemSetting::get('currency_symbol_position', 'before') ?? 'before'),
            'decimals' => max(0, min(4, $decimals)),
            'thousands' => (string) (SystemSetting::get('currency_thousands_separator', ',') ?? ','),
            'decimal' => (string) (SystemSetting::get('currency_decimal_separator', '.') ?? '.'),
        ];
    }

    public static function format(float|int|string|null $amount, bool $withCode = false): string
    {
        $config = self::config();
        $number = number_format((float) ($amount ?? 0), $config['decimals'], $config['decimal'], $config['thousands']);

        $formatted = $config['position'] === 'after'
            ? $number.' '.$config['symbol']
            : $config['symbol'].' '.$number;

        return $withCode ? $formatted.' '.$config['code'] : $formatted;
    }

    /** Compact form for KPI tiles: 1.2 rb / 350 jt / 1.4 M */
    public static function compact(float|int|string|null $amount): string
    {
        $config = self::config();
        $value = (float) ($amount ?? 0);
        $abs = abs($value);
        $sign = $value < 0 ? '-' : '';

        $units = [
            ['suffix' => ' T', 'factor' => 1_000_000_000_000],
            ['suffix' => ' M', 'factor' => 1_000_000_000],
            ['suffix' => ' jt', 'factor' => 1_000_000],
            ['suffix' => ' rb', 'factor' => 1_000],
        ];

        foreach ($units as $unit) {
            if ($abs >= $unit['factor']) {
                $scaled = $abs / $unit['factor'];
                $text = $scaled >= 100
                    ? number_format($scaled, 0, $config['decimal'], $config['thousands'])
                    : number_format($scaled, 1, $config['decimal'], $config['thousands']);

                return $sign.$config['symbol'].$text.$unit['suffix'];
            }
        }

        return self::format($value);
    }

    public static function number(float|int|string|null $amount, int $decimals = 0): string
    {
        $config = self::config();

        return number_format((float) ($amount ?? 0), $decimals, $config['decimal'], $config['thousands']);
    }

    /** Called after an admin changes currency settings. */
    public static function flush(): void
    {
        self::$config = null;
    }
}
