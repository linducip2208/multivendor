<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Support\Money;

/**
 * Komisi bertingkat deterministik (selaras tier registrasi vendor).
 *
 * Pure domain: tarif hanya fungsi GMV bulanan toko → settlement yang
 * dihitung hari ini dan bulan depan untuk input sama selalu identik.
 */
final class TieredCommission
{
    /** @var list<array{up_to:float, rate:float, tier:string}> */
    public const TIERS = [
        ['up_to' => 1000000, 'rate' => 8.0, 'tier' => 'starter'],
        ['up_to' => 10000000, 'rate' => 6.0, 'tier' => 'growth'],
        ['up_to' => 50000000, 'rate' => 4.5, 'tier' => 'scale'],
        ['up_to' => PHP_FLOAT_MAX, 'rate' => 3.0, 'tier' => 'enterprise'],
    ];

    /** @return array{rate:float, tier:string} */
    public static function tierFor(float $monthlyGmv): array
    {
        foreach (self::TIERS as $tier) {
            if ($monthlyGmv <= $tier['up_to']) {
                return ['rate' => $tier['rate'], 'tier' => $tier['tier']];
            }
        }

        return ['rate' => 3.0, 'tier' => 'enterprise'];
    }

    /** @return array{rate:float, tier:string, commission:float} */
    public static function forOrder(float $subtotal, float $monthlyGmv): array
    {
        $tier = self::tierFor(max(0.0, $monthlyGmv));
        $commission = Money::of($subtotal)->maxZero()
            ->multiply($tier['rate'] / 100)->maxZero()
            ->min(Money::of($subtotal));

        return ['rate' => $tier['rate'], 'tier' => $tier['tier'], 'commission' => $commission->toFloat()];
    }
}
