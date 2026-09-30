<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Models\Currency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Fasade engine: daftar aktif, default, konversi, format.
 *
 * Read path di-cache 1 jam; setiap perubahan kurs memanggil
 * RateProvider lalu clearCache(). Tidak menyentuh kalkulasi
 * checkout/payment existing — checkout memanggil convertMinor()
 * / Money secara eksplisit bila ingin multi-currency.
 */
final class CurrencyService
{
    private const CACHE_ACTIVE = 'currency.active';

    private const CACHE_DEFAULT = 'currency.default';

    private const CACHE_TTL = 3600;

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_ACTIVE);
        Cache::forget(self::CACHE_DEFAULT);
    }

    /** @return Collection<int, Currency> */
    public static function active(): Collection
    {
        return Cache::remember(self::CACHE_ACTIVE, self::CACHE_TTL, function () {
            return Currency::query()->active()->ordered()->get();
        });
    }

    public static function default(): ?Currency
    {
        return Cache::remember(self::CACHE_DEFAULT, self::CACHE_TTL, function () {
            return Currency::query()->where('is_default', true)->first()
                ?? Currency::query()->where('code', 'IDR')->first();
        });
    }

    public static function find(string $code): ?Currency
    {
        $code = strtoupper(trim($code));

        return static::active()->firstWhere('code', $code)
            ?? Currency::query()->where('code', $code)->first();
    }

    public static function require(string $code): Currency
    {
        $currency = static::find($code);

        if ($currency === null || ! (bool) $currency->is_active) {
            throw new \DomainException("Mata uang {$code} tidak aktif.");
        }

        return $currency;
    }

    public static function decimalsFor(string $code): int
    {
        return (int) static::require($code)->decimal_places;
    }

    public static function symbolFor(string $code): string
    {
        return (string) static::require($code)->symbol;
    }

    /** Bungkus minor int menjadi Money sesuai desimal currency. */
    public static function moneyFromMinor(int $minor, string $code): Money
    {
        $currency = static::require($code);

        return Money::fromMinor($minor, $currency->code, (int) $currency->decimal_places);
    }

    /**
     * Konversi Money ke kode tujuan (kurs string dari DB, tanpa float).
     */
    public static function convert(Money $money, string $toCode): Money
    {
        $from = static::require($money->code());
        $to = static::require($toCode);

        return CurrencyConverter::convert(
            $money,
            $to->code,
            (int) $to->decimal_places,
            (string) $from->exchange_rate,
            (string) $to->exchange_rate,
        );
    }

    /** Format Money sesuai locale (id-ID / en-US). */
    public static function format(Money $money, string $locale = 'id-ID'): string
    {
        try {
            $symbol = static::symbolFor($money->code());
        } catch (\Throwable) {
            $symbol = null;
        }

        return MoneyFormatter::format($money, $locale, $symbol);
    }
}
