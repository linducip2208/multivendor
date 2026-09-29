<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Customer acquisition, retention and value reporting.
 *
 * "Retention" here is purely factual: how many of last period's buyers bought
 * again in this period. No inference about a person's intent or wellbeing.
 */
final class CustomerAnalyticsService extends AnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function report(DateRange $range, int $perPage = 20, int $page = 1, string $search = ''): array
    {
        return $this->remember('customers', $range, function () use ($range, $perPage, $page, $search): array {
            $previous = $range->previous();

            $currentIds = $this->buyerIds($range);
            $previousIds = $this->buyerIds($previous);

            $total = (int) $currentIds->count();
            $previousTotal = (int) $previousIds->count();
            $returning = (int) $currentIds->intersect($previousIds)->count();
            $new = max(0, $total - $returning);

            $retained = $previousTotal > 0 ? round(($returning / $previousTotal) * 100, 2) : 0.0;

            return [
                'range' => $range->toArray(),
                'kpis' => [
                    'buyers' => ['value' => $total, 'label' => 'Pembeli', 'money' => false, 'trend' => $this->delta((float) $total, (float) $previousTotal)],
                    'new' => ['value' => $new, 'label' => 'Pembeli Baru', 'money' => false, 'trend' => $this->delta((float) $new, 0.0)],
                    'returning' => ['value' => $returning, 'label' => 'Pembeli Berulang', 'money' => false, 'trend' => $this->delta((float) $returning, 0.0)],
                    'retention' => ['value' => $retained, 'label' => 'Retensi', 'money' => false, 'hint' => 'Pembeli periode ini yang juga membeli periode lalu'],
                    'registered' => [
                        'value' => (int) User::query()
                            ->where('role', 'customer')
                            ->whereBetween('created_at', [$range->from, $range->to])
                            ->count(),
                        'label' => 'Pendaftaran Baru',
                        'money' => false,
                        'trend' => $this->delta(0.0, 0.0),
                    ],
                    'aov' => [
                        'value' => $total > 0 ? (float) $this->revenueOrders($range)->sum('total') / $total : 0.0,
                        'label' => 'Rata-rata per Pembeli',
                        'money' => true,
                        'trend' => $this->delta(0.0, 0.0),
                    ],
                ],
                'series' => $this->buyerSeries($range),
                'tiers' => $this->tiers($range),
                'locations' => $this->locations($range),
                'rows' => $this->leaderboard($range, $perPage, $page, $search),
            ];
        }, ['per_page' => $perPage, 'page' => $page, 'search' => $search]);
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    private function buyerIds(DateRange $range): \Illuminate\Support\Collection
    {
        return $this->revenueOrders($range)
            ->whereNotNull('customer_id')
            ->distinct()
            ->pluck('customer_id')
            ->map(fn ($id): int => (int) $id);
    }

    /**
     * @return array{labels: list<string>, buyers: list<int>, new_buyers: list<int>}
     */
    private function buyerSeries(DateRange $range): array
    {
        $expression = $this->dateExpression(DB::connection()->getDriverName());
        $labels = $range->labels();

        $rows = DB::table('orders')
            ->whereIn('order_status', self::revenueOrderStatuses())
            ->whereIn('payment_status', self::paidPaymentStatuses())
            ->whereBetween('created_at', [$range->from, $range->to])
            ->whereNotNull('customer_id')
            ->selectRaw($expression.' as bucket, COUNT(DISTINCT customer_id) as buyers')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $buyers = [];
        foreach ($rows as $row) {
            $buyers[(string) $row->bucket] = (int) $row->buyers;
        }

        $firstSeen = [];
        if ($this->has('users')) {
            $firstSeen = User::query()
                ->where('role', 'customer')
                ->whereBetween('created_at', [$range->from, $range->to])
                ->get(['id', 'created_at'])
                ->mapWithKeys(fn (User $user): array => [$user->created_at->toDateString() => $user->id])
                ->all();
        }

        $newBuckets = [];
        foreach ($firstSeen as $day => $_) {
            $newBuckets[$day] = ($newBuckets[$day] ?? 0) + 1;
        }

        return [
            'labels' => $labels,
            'buyers' => $this->densify($labels, $buyers, 'buyers'),
            'new_buyers' => $this->densify($labels, $newBuckets, 'new_buyers'),
        ];
    }

    /**
     * Spend bands derived from realised order value only.
     *
     * @return list<array{label: string, min: float, count: int, revenue: float}>
     */
    private function tiers(DateRange $range): array
    {
        $rows = DB::table('orders')
            ->whereIn('order_status', self::revenueOrderStatuses())
            ->whereIn('payment_status', self::paidPaymentStatuses())
            ->whereBetween('created_at', [$range->from, $range->to])
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, SUM(total) as spend, COUNT(*) as orders')
            ->groupBy('customer_id')
            ->get();

        $bands = [
            ['label' => 'Sekali Beli', 'min' => 1],
            ['label' => '2-3 Kali', 'min' => 2],
            ['label' => '4-5 Kali', 'min' => 4],
            ['label' => '6-10 Kali', 'min' => 6],
            ['label' => '11+ Kali', 'min' => 11],
        ];

        $out = [];
        foreach ($bands as $index => $band) {
            $out[$index] = ['label' => $band['label'], 'min' => (float) $band['min'], 'count' => 0, 'revenue' => 0.0];
        }

        foreach ($rows as $row) {
            $orders = (int) $row->orders;
            $index = match (true) {
                $orders <= 1 => 0,
                $orders <= 3 => 1,
                $orders <= 5 => 2,
                $orders <= 10 => 3,
                default => 4,
            };
            $out[$index]['count']++;
            $out[$index]['revenue'] += (float) $row->spend;
        }

        return array_map(fn (array $row): array => [
            'label' => $row['label'],
            'count' => $row['count'],
            'revenue' => round($row['revenue'], 2),
        ], $out);
    }

    /**
     * @return list<array{province: string, customers: int, orders: int, revenue: float}>
     */
    private function locations(DateRange $range): array
    {
        $rows = DB::table('orders')
            ->whereIn('order_status', self::revenueOrderStatuses())
            ->whereIn('payment_status', self::paidPaymentStatuses())
            ->whereBetween('created_at', [$range->from, $range->to])
            ->whereNotNull('customer_id')
            ->selectRaw('shipping_address, COUNT(*) as orders, COUNT(DISTINCT customer_id) as customers, SUM(total) as revenue')
            ->groupBy('shipping_address')
            ->limit(200)
            ->get();

        $buckets = [];

        foreach ($rows as $row) {
            $address = is_string($row->shipping_address) ? json_decode($row->shipping_address, true) : $row->shipping_address;
            if (! is_array($address)) {
                continue;
            }
            $province = trim((string) ($address['province'] ?? $address['state'] ?? ''));
            if ($province === '') {
                continue;
            }
            $buckets[$province] ??= ['customers' => 0, 'orders' => 0, 'revenue' => 0.0];
            $buckets[$province]['orders'] += (int) $row->orders;
            $buckets[$province]['revenue'] += (float) $row->revenue;
            $buckets[$province]['customers'] = max($buckets[$province]['customers'], (int) $row->customers);
        }

        arsort($buckets);

        $out = [];
        foreach (array_slice($buckets, 0, 10, true) as $province => $bucket) {
            $out[] = [
                'province' => (string) $province,
                'customers' => $bucket['customers'],
                'orders' => $bucket['orders'],
                'revenue' => round($bucket['revenue'], 2),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function leaderboard(DateRange $range, int $perPage, int $page, string $search): array
    {
        $query = $this->revenueOrders($range);

        if ($search !== '') {
            $query->whereHas('customer', fn (Builder $c) => $c->where('name', 'like', '%'.$search.'%'));
        }

        $rows = $query
            ->select('customer_id')
            ->selectRaw('SUM(total) as spend, COUNT(*) as orders, MAX(created_at) as last_order')
            ->groupBy('customer_id')
            ->orderByDesc('spend')
            ->forPage(max(1, $page), max(5, min(100, $perPage)))
            ->get();

        $ids = $rows->pluck('customer_id')->filter()->map(fn ($id): int => (int) $id)->all();
        $customers = $ids === []
            ? collect()
            : User::query()->whereIn('id', $ids)->get(['id', 'name', 'email', 'phone'])->keyBy('id');

        return [
            'rows' => $rows->map(fn ($row): array => [
                'customer_id' => (int) $row->customer_id,
                'name' => (string) ($customers[(int) $row->customer_id]->name ?? 'Pelanggan #'.$row->customer_id),
                'email' => (string) ($customers[(int) $row->customer_id]->email ?? ''),
                'phone' => (string) ($customers[(int) $row->customer_id]->phone ?? ''),
                'orders' => (int) $row->orders,
                'spend' => (float) $row->spend,
                'last_order' => (string) ($row->last_order ?? ''),
            ])->all(),
        ];
    }

    /**
     * Vendor-side counterpart used by `analytics.vendorSales`.
     *
     * @return array<string, mixed>
     */
    public function vendorSalesReport(DateRange $range, int $perPage = 25, int $page = 1, string $search = ''): array
    {
        $query = Shop::query()->where('status', 'active');

        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $perPage = max(5, min(100, $perPage));
        $page = max(1, $page);
        $total = (clone $query)->count();
        $rows = $query->orderByDesc('sold_count')->forPage($page, $perPage)->get();

        $ids = $rows->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $stats = $ids === [] ? [] : DB::table('transactions')
            ->whereIn('shop_id', $ids)
            ->whereIn('status', self::successfulTransactionStatuses())
            ->whereBetween('created_at', [$range->from, $range->to])
            ->groupBy('shop_id')
            ->selectRaw('shop_id, SUM(amount) as revenue, SUM(admin_commission) as commission, SUM(vendor_amount) as payout, COUNT(*) as transactions')
            ->get()
            ->keyBy('shop_id')
            ->all();

        return [
            'range' => $range->toArray(),
            'kpis' => [
                'shops' => ['value' => (int) $total, 'label' => 'Toko Aktif', 'money' => false, 'trend' => $this->delta(0.0, 0.0)],
                'revenue' => [
                    'value' => (float) array_sum(array_map(fn ($s): float => (float) $s->revenue, $stats)),
                    'label' => 'Omzet Mitra',
                    'money' => true,
                    'trend' => $this->delta(0.0, 0.0),
                ],
                'commission' => [
                    'value' => (float) array_sum(array_map(fn ($s): float => (float) $s->commission, $stats)),
                    'label' => 'Komisi',
                    'money' => true,
                    'trend' => $this->delta(0.0, 0.0),
                ],
                'payout' => [
                    'value' => (float) array_sum(array_map(fn ($s): float => (float) $s->payout, $stats)),
                    'label' => 'Dibayar ke Vendor',
                    'money' => true,
                    'trend' => $this->delta(0.0, 0.0),
                ],
            ],
            'rows' => $rows->map(function (Shop $shop) use ($stats): array {
                $stat = $stats[$shop->id] ?? null;

                return [
                    'id' => (int) $shop->id,
                    'name' => (string) $shop->name,
                    'city' => (string) ($shop->city ?? '-'),
                    'products' => (int) $shop->product_count,
                    'rating' => (float) $shop->rating_average,
                    'sold' => (int) $shop->sold_count,
                    'revenue' => (float) ($stat->revenue ?? 0),
                    'commission' => (float) ($stat->commission ?? 0),
                    'payout' => (float) ($stat->payout ?? 0),
                    'transactions' => (int) ($stat->transactions ?? 0),
                ];
            })->all(),
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    private function dateExpression(string $driver): string
    {
        return match ($driver) {
            'sqlite' => 'date(created_at)',
            'pgsql' => "to_char(created_at, 'YYYY-MM-DD')",
            default => 'DATE(created_at)',
        };
    }
}
