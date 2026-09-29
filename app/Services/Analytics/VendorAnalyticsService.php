<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Order;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Seller performance: revenue share, commission, order mix and the sellers
 * whose results moved most in the reporting window.
 */
final class VendorAnalyticsService extends AnalyticsService
{
    public const PAGE_SIZE = 20;

    /**
     * @return array<string, mixed>
     */
    public function report(DateRange $range, int $perPage = self::PAGE_SIZE, int $page = 1, string $search = ''): array
    {
        return $this->remember('vendors', $range, function () use ($range, $perPage, $page, $search): array {
            $query = Shop::query()->where('status', 'active');

            if ($search !== '') {
                $query->where('name', 'like', '%'.$search.'%');
            }

            $page = max(1, $page);
            $perPage = max(5, min(100, $perPage));
            $total = (int) (clone $query)->count();

            $ids = (clone $query)->orderByDesc('sold_count')->forPage($page, $perPage)->pluck('id')
                ->map(fn ($id): int => (int) $id)->all();

            $stats = $this->statsFor($ids, $range);
            $previous = $this->statsFor($ids, $range->previous());

            $rows = Shop::query()->whereIn('id', $ids)->orderByDesc('sold_count')->get()
                ->map(function (Shop $shop) use ($stats, $previous): array {
                    $stat = $stats[(int) $shop->id] ?? null;
                    $before = $previous[(int) $shop->id] ?? null;
                    $revenue = (float) ($stat->revenue ?? 0);
                    $previousRevenue = (float) ($before->revenue ?? 0);

                    return [
                        'id' => (int) $shop->id,
                        'name' => (string) $shop->name,
                        'city' => (string) ($shop->city ?? '-'),
                        'status' => (string) $shop->status,
                        'products' => (int) $shop->product_count,
                        'rating' => (float) $shop->rating_average,
                        'rating_count' => (int) $shop->rating_count,
                        'sold' => (int) $shop->sold_count,
                        'orders' => (int) ($stat->orders ?? 0),
                        'revenue' => $revenue,
                        'commission' => (float) ($stat->commission ?? 0),
                        'payout' => (float) ($stat->payout ?? 0),
                        'items' => (int) ($stat->items ?? 0),
                        'trend' => $this->delta($revenue, $previousRevenue),
                        'url' => route('admin.vendors.show', $shop->id),
                    ];
                })
                ->all();

            $totalRevenue = (float) DB::table('orders')
                ->whereIn('order_status', self::revenueOrderStatuses())
                ->whereIn('payment_status', self::paidPaymentStatuses())
                ->whereBetween('created_at', [$range->from, $range->to])
                ->sum('total');

            $sumRevenue = (float) array_sum(array_map(fn (array $row): float => (float) $row['revenue'], $rows));
            $sumCommission = (float) array_sum(array_map(fn (array $row): float => (float) $row['commission'], $rows));

            return [
                'range' => $range->toArray(),
                'kpis' => [
                    'shops' => ['value' => $total, 'label' => 'Toko Aktif', 'money' => false, 'trend' => $this->delta(0.0, 0.0)],
                    'revenue' => ['value' => $sumRevenue, 'label' => 'Omzet Mitra', 'money' => true, 'trend' => $this->delta(0.0, 0.0)],
                    'commission' => ['value' => $sumCommission, 'label' => 'Komisi', 'money' => true, 'trend' => $this->delta(0.0, 0.0)],
                    'payout' => ['value' => (float) array_sum(array_map(fn (array $row): float => (float) $row['payout'], $rows)), 'label' => 'Dibayar ke Vendor', 'money' => true, 'trend' => $this->delta(0.0, 0.0)],
                    'share' => [
                        'value' => $totalRevenue > 0 ? round(($sumRevenue / $totalRevenue) * 100, 1) : 0.0,
                        'label' => 'Porsi Omzet Mitra',
                        'money' => false,
                        'hint' => 'Omzet mitra pada halaman ini dibagi seluruh omzet platform',
                    ],
                ],
                'rows' => $rows,
                'series' => $this->series($range),
                'tiers' => $this->tiers($rows),
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => (int) max(1, (int) ceil($total / $perPage)),
                ],
            ];
        }, ['per_page' => $perPage, 'page' => $page, 'search' => $search]);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, object>
     */
    private function statsFor(array $ids, DateRange $range): array
    {
        if ($ids === []) {
            return [];
        }

        $orders = DB::table('orders')
            ->whereIn('shop_id', $ids)
            ->whereIn('order_status', self::revenueOrderStatuses())
            ->whereIn('payment_status', self::paidPaymentStatuses())
            ->whereBetween('created_at', [$range->from, $range->to])
            ->groupBy('shop_id')
            ->selectRaw('shop_id, COUNT(*) as orders, SUM(total) as revenue, SUM(shipping_cost) as shipping')
            ->get()
            ->keyBy('shop_id');

        $transactions = $this->has('transactions')
            ? DB::table('transactions')
                ->whereIn('shop_id', $ids)
                ->whereIn('status', self::successfulTransactionStatuses())
                ->whereBetween('created_at', [$range->from, $range->to])
                ->groupBy('shop_id')
                ->selectRaw('shop_id, COALESCE(SUM(admin_commission), 0) as commission, COALESCE(SUM(vendor_amount), 0) as payout')
                ->get()
                ->keyBy('shop_id')
            : collect();

        $items = $this->has('order_items')
            ? DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereIn('orders.shop_id', $ids)
                ->whereIn('orders.order_status', self::revenueOrderStatuses())
                ->whereBetween('orders.created_at', [$range->from, $range->to])
                ->groupBy('orders.shop_id')
                ->selectRaw('orders.shop_id, SUM(order_items.quantity) as items')
                ->get()
                ->keyBy('shop_id')
            : collect();

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = (object) [
                'orders' => (int) ($orders[$id]->orders ?? 0),
                'revenue' => (float) ($orders[$id]->revenue ?? 0),
                'shipping' => (float) ($orders[$id]->shipping ?? 0),
                'commission' => (float) ($transactions[$id]->commission ?? 0),
                'payout' => (float) ($transactions[$id]->payout ?? 0),
                'items' => (int) ($items[$id]->items ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array{labels: list<string>, revenue: list<float>, orders: list<int>}
     */
    private function series(DateRange $range): array
    {
        $expression = match (DB::connection()->getDriverName()) {
            'sqlite' => 'date(orders.created_at)',
            'pgsql' => "to_char(orders.created_at, 'YYYY-MM-DD')",
            default => 'DATE(orders.created_at)',
        };

        $rows = DB::table('orders')
            ->whereIn('order_status', self::revenueOrderStatuses())
            ->whereIn('payment_status', self::paidPaymentStatuses())
            ->whereBetween('created_at', [$range->from, $range->to])
            ->selectRaw($expression.' as bucket, SUM(total) as revenue, COUNT(*) as orders')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $revenue = [];
        $orders = [];
        foreach ($rows as $row) {
            $revenue[(string) $row->bucket] = (float) $row->revenue;
            $orders[(string) $row->bucket] = (int) $row->orders;
        }

        $labels = $range->labels();

        return [
            'labels' => $labels,
            'revenue' => $this->densify($labels, $revenue),
            'orders' => $this->densify($labels, $orders, 'orders'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{label: string, count: int, revenue: float}>
     */
    private function tiers(array $rows): array
    {
        $sorted = $rows;
        usort($sorted, fn (array $a, array $b): int => (float) $b['revenue'] <=> (float) $a['revenue']);

        $out = [];
        $total = (float) array_sum(array_map(fn (array $row): float => (float) $row['revenue'], $rows));

        $bands = [
            ['label' => '5 Teratas', 'limit' => 5],
            ['label' => '6-20', 'limit' => 20],
            ['label' => '21-50', 'limit' => 50],
            ['label' => 'Sisanya', 'limit' => PHP_INT_MAX],
        ];

        $offset = 0;
        foreach ($bands as $band) {
            $slice = array_slice($sorted, $offset, $band['limit'] === PHP_INT_MAX ? null : $band['limit'] - $offset);
            $revenue = (float) array_sum(array_map(fn (array $row): float => (float) $row['revenue'], $slice));

            $out[] = [
                'label' => (string) $band['label'],
                'count' => count($slice),
                'revenue' => round($revenue, 2),
                'share' => $total > 0 ? round(($revenue / $total) * 100, 1) : 0.0,
            ];

            $offset += count($slice);
            if ($offset >= count($sorted)) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return Builder<Shop>
     */
    public function activeShops(): Builder
    {
        return Shop::query()->where('status', 'active');
    }
}
