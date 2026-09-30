<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Models\Currency;
use Illuminate\Support\Carbon;

/**
 * Penyedia kurs manual.
 *
 * - Sumber default "manual" (diubah operator / seeder awal).
 * - refreshFromArray() dipakai command skeleton currency:rates-refresh
 *   dan calon job terjadwal — TANPA kredensial / HTTP call di sini.
 * - Semua kurs disimpan sebagai string desimal, tanpa float.
 */
final class RateProvider
{
    /** Ambil kurs aktif sebagai string desimal; lempar bila tidak dikenal. */
    public function getRate(string $code): string
    {
        $code = strtoupper(trim($code));
        $rate = Currency::query()->where('code', $code)->where('is_active', true)->value('exchange_rate');

        if ($rate === null) {
            throw new \DomainException("Kurs {$code} tidak tersedia.");
        }

        return (string) $rate;
    }

    /** Semua kurs aktif: [CODE => "rate-string"]. */
    public function allRates(): array
    {
        return Currency::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->pluck('exchange_rate', 'code')
            ->map(fn ($rate) => (string) $rate)
            ->all();
    }

    /**
     * Set satu kurs manual (validasi string desimal > 0, tanpa float).
     */
    public function setRate(string $code, string $rate, string $source = 'manual'): Currency
    {
        $code = strtoupper(trim($code));
        self::assertRateString($rate);

        $currency = Currency::query()->where('code', $code)->firstOrFail();
        $currency->exchange_rate = $rate;
        $currency->rate_source = substr(trim($source) === '' ? 'manual' : trim($source), 0, 30);
        $currency->rate_updated_at = Carbon::now();
        $currency->save();

        return $currency->fresh() ?? $currency;
    }

    /**
     * Refresh massal dari array [CODE => "rate-string"].
     * Melewatkan kode tak dikenal / kurs invalid; kembalikan jumlah ter-update.
     */
    public function refreshFromArray(array $codeToRate, string $source = 'manual'): int
    {
        $updated = 0;

        foreach ($codeToRate as $code => $rate) {
            $code = strtoupper(trim((string) $code));
            $rate = trim((string) $rate);

            if ($code === '' || ! self::isValidRate($rate)) {
                continue;
            }

            $currency = Currency::query()->where('code', $code)->first();
            if ($currency === null) {
                continue;
            }

            $currency->exchange_rate = $rate;
            $currency->rate_source = substr(trim($source) === '' ? 'manual' : trim($source), 0, 30);
            $currency->rate_updated_at = Carbon::now();
            $currency->save();
            $updated++;
        }

        return $updated;
    }

    /**
     * Parse pasangan CLI "CODE=RATE" (dipakai command skeleton).
     *
     * @param  string[]  $pairs
     * @return array<string, string>
     */
    public static function parseCliPairs(array $pairs): array
    {
        $out = [];

        foreach ($pairs as $pair) {
            $pair = trim((string) $pair);
            if ($pair === '' || ! str_contains($pair, '=')) {
                continue;
            }
            [$code, $rate] = explode('=', $pair, 2);
            $code = strtoupper(trim($code));
            $rate = trim($rate);

            if ($code !== '' && self::isValidRate($rate)) {
                $out[$code] = $rate;
            }
        }

        return $out;
    }

    public static function isValidRate(string $rate): bool
    {
        $rate = trim($rate);

        return (bool) preg_match('/^\d+(\.\d+)?$/', $rate)
            && function_exists('bccomp')
            && bccomp($rate, '0', 12) > 0;
    }

    private static function assertRateString(string $rate): void
    {
        if (! self::isValidRate($rate)) {
            throw new \InvalidArgumentException('exchange_rate harus string desimal > 0 (tanpa float).');
        }
    }
}
