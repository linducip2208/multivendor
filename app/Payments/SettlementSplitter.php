<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\PaymentGroup;
use App\Support\Money;

/**
 * Settlement multi-vendor deterministik.
 *
 * - Urut shop_id menaik; hitung dalam minor unit (integer) agar tak drift.
 * - Sisa pembulatan selalu ke shop_id terkecil -> hasil stabil antar run.
 * - Biaya provider dikurangkan proporsional per toko (minor unit juga).
 *
 * @return array<int, array{shop_id:int,gross:float,fee_share:float,net:float}>
 */
final class SettlementSplitter
{
    public static function split(PaymentGroup $group, float $providerFee = 0.0): array
    {
        $orders = $group->orders()->orderBy('shop_id')->orderBy('id')->get();
        if ($orders->isEmpty()) {
            return [];
        }

        $grossMinors = [];
        $totalMinor = 0;
        foreach ($orders as $order) {
            $minor = Money::of($order->total)->minor;
            // Gabungkan order satu toko yang sama.
            $grossMinors[(int) $order->shop_id] = ($grossMinors[(int) $order->shop_id] ?? 0) + $minor;
            $totalMinor += $minor;
        }
        ksort($grossMinors);

        $feeMinor = Money::of(max(0.0, $providerFee))->minor;
        $out = [];
        $allocatedFee = 0;
        $shopIds = array_keys($grossMinors);
        $lastShop = end($shopIds);

        foreach ($grossMinors as $shopId => $gross) {
            if ($shopId === $lastShop) {
                $share = $feeMinor - $allocatedFee; // sisa ke toko terakhir (deterministik)
            } else {
                $share = $totalMinor > 0 ? (int) floor($feeMinor * $gross / $totalMinor) : 0;
                $allocatedFee += $share;
            }
            $share = max(0, min($share, $gross));
            $out[] = [
                'shop_id' => $shopId,
                'gross' => round($gross / 100, 2),
                'fee_share' => round($share / 100, 2),
                'net' => round(($gross - $share) / 100, 2),
            ];
        }

        return $out;
    }
}
