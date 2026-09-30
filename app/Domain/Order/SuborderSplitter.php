<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Support\Money;

/**
 * Suborder deterministik per vendor.
 *
 * Pure domain (tanpa DB): dari kuotasi per toko menghasilkan alokasi
 * ongkir/pajak/diskon/komisi yang jumlahnya selalu rekonsiliasi ke total
 * induk. Deterministik = input sama → output identik (urut shop_id,
 * aritmetika minor-unit, sisa pembulatan diserap baris terakhir).
 *
 * Refund responsibility: vendor menanggung porsi item+pajak+ongkir miliknya,
 * platform menanggung pembalikan komisinya.
 */
final class SuborderSplitter
{
    /**
     * @param  list<array{shop_id:int, subtotal:float|int|string, tax:float|int|string, shipping:float|int|string, discount:float|int|string, commission_rate:float}>  $quotes
     * @return array{vendors:list<array<string,mixed>>, totals:array<string,float>}
     */
    public static function split(array $quotes): array
    {
        usort($quotes, fn (array $a, array $b) => ((int) $a['shop_id']) <=> ((int) $b['shop_id']));

        $vendors = [];
        $tSub = Money::zero();
        $tTax = Money::zero();
        $tShip = Money::zero();
        $tDisc = Money::zero();
        $tComm = Money::zero();
        $tNet = Money::zero();

        foreach ($quotes as $q) {
            $subtotal = Money::of($q['subtotal'] ?? 0);
            $tax = Money::of($q['tax'] ?? 0);
            $shipping = Money::of($q['shipping'] ?? 0);
            $discount = Money::of($q['discount'] ?? 0)->maxZero()->min($subtotal->add($tax)->add($shipping));
            $rate = max(0.0, (float) ($q['commission_rate'] ?? 0));
            $commission = $subtotal->multiply($rate / 100)->maxZero()->min($subtotal);
            $net = $subtotal->add($tax)->add($shipping)->subtract($discount)->maxZero();
            $vendorPayable = $net->subtract($commission)->maxZero();

            $vendors[] = [
                'shop_id' => (int) $q['shop_id'],
                'subtotal' => $subtotal->toFloat(),
                'tax' => $tax->toFloat(),
                'shipping' => $shipping->toFloat(),
                'discount' => $discount->toFloat(),
                'commission_rate' => $rate,
                'commission' => $commission->toFloat(),
                'net' => $net->toFloat(),
                'vendor_payable' => $vendorPayable->toFloat(),
                // Tanggung jawab refund per vendor (proporsional, deterministik).
                'refund_responsibility' => [
                    'vendor_bears' => $vendorPayable->toFloat(),
                    'platform_reverses' => $commission->toFloat(),
                ],
            ];

            $tSub = $tSub->add($subtotal);
            $tTax = $tTax->add($tax);
            $tShip = $tShip->add($shipping);
            $tDisc = $tDisc->add($discount);
            $tComm = $tComm->add($commission);
            $tNet = $tNet->add($net);
        }

        return [
            'vendors' => $vendors,
            'totals' => [
                'subtotal' => $tSub->toFloat(),
                'tax' => $tTax->toFloat(),
                'shipping' => $tShip->toFloat(),
                'discount' => $tDisc->toFloat(),
                'commission' => $tComm->toFloat(),
                'grand_total' => $tNet->toFloat(),
            ],
        ];
    }

    /**
     * Alokasi diskon induk ke vendor proporsional subtotal (minor-unit,
     * sisa diserap vendor terakhir) — deterministik & rekonsiliasi pas.
     *
     * @param  array<int,float>  $eligibleSubtotals  shop_id => subtotal
     * @return array<int,float>  shop_id => discount share
     */
    public static function allocateDiscount(array $eligibleSubtotals, float|int|string $discount): array
    {
        ksort($eligibleSubtotals);
        $total = Money::sum(array_map(fn ($v) => Money::of($v), array_values($eligibleSubtotals)));
        $pool = Money::of($discount)->maxZero()->min($total);
        $out = [];
        $allocated = Money::zero();
        $keys = array_keys($eligibleSubtotals);
        $last = end($keys);

        foreach ($eligibleSubtotals as $shopId => $sub) {
            if ($shopId === $last) {
                $share = $pool->subtract($allocated);
            } else {
                $share = $total->isPositive()
                    ? $pool->multiply(Money::of($sub)->minor / max(1, $total->minor))
                    : Money::zero();
                $allocated = $allocated->add($share);
            }
            $out[(int) $shopId] = $share->toFloat();
        }

        return $out;
    }
}
