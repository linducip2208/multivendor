<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * The executive tile deck.
 *
 * Every figure is a plain scalar ready for `x-admin.stat`; no Blade ever sums
 * a column. Comparison is against the immediately preceding window of the same
 * length so a "last 30 days" tile always has something meaningful to compare to.
 */
final class ExecutiveAnalyticsService extends AnalyticsService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function summary(DateRange $range): array
    {
        return $this->remember('executive', $range, function () use ($range): array {
            $current = $this->figures($range);
            $previous = $this->figures($range->previous());

            $tiles = [];
            foreach ($current as $key => $figure) {
                $tiles[$key] = $figure + [
                    'trend' => $this->delta((float) $figure['value'], (float) $previous[$key]['value']),
                ];
            }

            $tiles['series'] = $this->dailySeries($range);
            $tiles['range'] = $range->toArray();
            $tiles['compare'] = [
                'previous_from' => $range->previous()->from->toDateString(),
                'previous_to' => $range->previous()->to->toDateString(),
                'label' => 'Dibandingkan periode sebelumnya ('.$range->previous()->from->format('d M').' - '.$range->previous()->to->format('d M Y').')',
            ];

            return $tiles;
        });
    }

    /**
     * @return array<string, array{value: float|int, label: string, money: bool, hint: string}>
     */
    private function figures(DateRange $range): array
    {
        $orders = $this->orders($range);
        $revenue = $this->revenueOrders($range);

        $gmv = (float) $revenue->sum('total');
        $orderCount = (int) $revenue->count();
        $aov = $orderCount > 0 ? $gmv / $orderCount : 0.0;
        $net = $gmv - $this->refundedTotal($range);

        $transactions = $this->has('transactions')
            ? \App\Models\Transaction::query()->whereBetween('created_at', [$range->from, $range->to])
                ->whereIn('status', self::successfulTransactionStatuses())
            : null;

        $commission = $transactions ? (float) $transactions->sum('admin_commission') : 0.0;
        $vendorPayout = $transactions ? (float) $transactions->sum('vendor_amount') : 0.0;

        $completedPayouts = $this->has('vendor_withdraw_requests')
            ? (float) \App\Models\VendorWithdrawRequest::query()
                ->whereIn('status', ['approved', 'completed'])
                ->whereBetween('created_at', [$range->from, $range->to])
                ->sum('amount')
            : 0.0;

        $allOrders = (int) $orders->count();
        $sessionCount = (int) $this->sessions($range);

        return [
            'gmv' => ['value' => $gmv, 'label' => 'GMV', 'money' => true, 'hint' => 'Nilai seluruh pesanan terbayar'],
            'net_revenue' => ['value' => $net, 'label' => 'Pendapatan Bersih', 'money' => true, 'hint' => 'GMV dikurangi refund'],
            'orders' => ['value' => $orderCount, 'label' => 'Pesanan', 'money' => false, 'hint' => 'Pesanan terbayar pada periode ini'],
            'aov' => ['value' => $aov, 'label' => 'Rata-rata Order', 'money' => true, 'hint' => 'GMV divided by paid orders'],
            'refunds' => ['value' => $this->refundedTotal($range), 'label' => 'Refund', 'money' => true, 'hint' => 'Nilai refund sukses'],
            'commission' => ['value' => $commission, 'label' => 'Komisi Platform', 'money' => true, 'hint' => 'Komisi marketplace'],
            'payouts' => ['value' => $vendorPayout, 'label' => 'Pendapatan Vendor', 'money' => true, 'hint' => 'Nilai yang menjadi hak seller'],
            'payout_requests' => ['value' => $completedPayouts, 'label' => 'Payout Disetujui', 'money' => true, 'hint' => 'Penarikan saldo disetujui'],
            'customers' => [
                'value' => (int) $revenue->distinct()->count('customer_id'),
                'label' => 'Pelanggan',
                'money' => false,
                'hint' => 'Pelanggan unik yang pernah membeli',
            ],
            'vendors' => [
                'value' => (int) $revenue->whereNotNull('shop_id')->distinct()->count('shop_id'),
                'label' => 'Vendor',
                'money' => false,
                'hint' => 'Toko dengan penjualan pada periode ini',
            ],
            'products' => [
                'value' => $this->has('products') ? (int) \App\Models\Product::query()->where('status', 'approved')->count() : 0,
                'label' => 'Produk Aktif',
                'money' => false,
                'hint' => 'Produk berstatus approved',
            ],
            'conversion' => [
                'value' => $this->conversionRate($range, $allOrders, $sessionCount),
                'label' => 'Konversi',
                'money' => false,
                'hint' => 'Pesanan per sesi',
            ],
            'cancelled' => [
                'value' => (int) $orders->whereIn('order_status', self::failedOrderStatuses())->count(),
                'label' => 'Pesanan Gagal',
                'money' => false,
                'hint' => 'Dibatalkan atau gagal',
            ],
        ];
    }

    /**
     * @return array{labels: list<string>, gmv: list<float>, orders: list<float>}
     */
    public function dailySeries(DateRange $range): array
    {
        $step = $range->step();
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $expression = $this->dateExpression($driver);

        $rows = \Illuminate\Support\Facades\DB::table('orders')
            ->whereIn('order_status', self::revenueOrderStatuses())
            ->whereIn('payment_status', self::paidPaymentStatuses())
            ->whereBetween('created_at', [$range->from, $range->to])
            ->selectRaw($expression.' as bucket, SUM(total) as amount, COUNT(*) as orders')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $amounts = [];
        $counts = [];

        foreach ($rows as $row) {
            $amounts[(string) $row->bucket] = (float) $row->amount;
            $counts[(string) $row->bucket] = (int) $row->orders;
        }

        $labels = $range->labels();

        return [
            'labels' => array_map(fn (string $label): string => $label, $labels),
            'gmv' => $this->densify($labels, $amounts),
            'orders' => $this->densify($labels, $counts, 'orders'),
            'step' => $step,
        ];
    }

    private function refundedTotal(DateRange $range): float
    {
        if (! $this->has('refunds')) {
            return 0.0;
        }

        return (float) \App\Models\Refund::query()
            ->where('status', 'succeeded')
            ->whereBetween('succeeded_at', [$range->from, $range->to])
            ->sum('amount');
    }

    private function sessions(DateRange $range): int
    {
        if (! $this->has('abandoned_carts')) {
            return 0;
        }

        return (int) \App\Models\AbandonedCart::query()
            ->whereNotNull('session_id')
            ->whereBetween('created_at', [$range->from, $range->to])
            ->distinct()
            ->count('session_id');
    }

    private function conversionRate(DateRange $range, int $orders, int $sessions): float
    {
        if ($sessions <= 0) {
            return 0.0;
        }

        return round(($orders / $sessions) * 100, 2);
    }

    private function dateExpression(string $driver): string
    {
        return match ($driver) {
            'sqlite' => "date(created_at)",
            'pgsql' => "to_char(created_at, 'YYYY-MM-DD')",
            default => 'DATE(created_at)',
        };
    }
}
