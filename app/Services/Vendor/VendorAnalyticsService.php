<?php

declare(strict_types=1);

namespace App\Services\Vendor;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\DateRange;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Vendor-scoped analytics.
 *
 * Wraps the shared analytics vocabulary with a shop filter that is applied
 * inside the query, never in PHP after the rows are fetched.
 */
final class VendorAnalyticsService
{
    public function __construct(private readonly VendorScope $scope) {}

    public function overview(DateRange $range): array
    {
        $shopId = $this->scope->shopId();

        $revenue = $this->revenue($shopId, $range);
        $previous = $range->previous();

        $currentGross = (float) $this->revenue($shopId, $range)->sum('sub_total');
        $currentOrders = (int) $this->revenue($shopId, $range)->count();
        $previousGross = (float) $this->revenue($shopId, $previous)->sum('sub_total');
        $previousOrders = (int) $this->revenue($shopId, $previous)->count();

        $all = Order::query()->where('shop_id', $shopId)->whereBetween('created_at', [$range->from, $range->to]);

        return [
            'range' => $range,
            'revenue' => Money::of($currentGross),
            'orders' => $currentOrders,
            'aov' => $currentOrders > 0 ? Money::of($currentGross)->multiply(1 / $currentOrders) : Money::zero(),
            'growth' => $this->growth($currentGross, $previousGross),
            'order_growth' => $this->growth((float) $currentOrders, (float) $previousOrders),
            'cancelled' => (int) (clone $all)->whereIn('order_status', AnalyticsService::failedOrderStatuses())->count(),
            'cancellation_rate' => $this->rate(
                (int) (clone $all)->whereIn('order_status', AnalyticsService::failedOrderStatuses())->count(),
                (int) (clone $all)->count()
            ),
            'new_customers' => (int) DB::table('orders')
                ->where('shop_id', $shopId)
                ->whereBetween('created_at', [$range->from, $range->to])
                ->whereNotNull('customer_id')
                ->distinct()
                ->count('customer_id'),
            'ratings' => (float) ProductReview::query()
                ->where('shop_id', $shopId)
                ->whereBetween('created_at', [$range->from, $range->to])
                ->avg('rating'),
            'series' => $this->series($shopId, $range),
            'status_mix' => $this->statusMix($shopId, $range),
        ];
    }

    public function sales(DateRange $range): array
    {
        $shopId = $this->scope->shopId();

        $rows = $this->revenue($shopId, $range)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['orders.id', 'orders.order_number', 'orders.created_at', 'orders.sub_total', 'orders.tax', 'orders.total', 'orders.order_status', 'orders.payment_status', 'orders.customer_id']);

        $customers = User::query()
            ->whereIn('id', $rows->pluck('customer_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        return [
            'range' => $range,
            'rows' => $rows->map(function (Order $order) use ($customers): array {
                return [
                    'order' => $order,
                    'customer' => $customers[$order->customer_id] ?? 'Pelanggan',
                    'total' => Money::of($order->total),
                ];
            }),
            'summary' => [
                'gross' => Money::of((float) (clone $this->revenue($shopId, $range))->sum('sub_total')),
                'tax' => Money::of((float) (clone $this->revenue($shopId, $range))->sum('tax')),
                'orders' => (int) (clone $this->revenue($shopId, $range))->count(),
            ],
            'top_days' => $this->topDays($shopId, $range),
        ];
    }

    public function customers(DateRange $range): array
    {
        $shopId = $this->scope->shopId();

        $rows = DB::table('orders')
            ->where('shop_id', $shopId)
            ->whereBetween('created_at', [$range->from, $range->to])
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) as orders, SUM(sub_total) as spend, MAX(created_at) as last_order_at')
            ->orderByDesc('spend')
            ->limit(50)
            ->get();

        $customers = User::query()
            ->whereIn('id', $rows->pluck('customer_id')->all())
            ->pluck('name', 'id');

        return [
            'range' => $range,
            'rows' => $rows->map(fn (object $row): array => [
                'id' => (int) $row->customer_id,
                'name' => (string) ($customers[$row->customer_id] ?? 'Pelanggan #'.$row->customer_id),
                'orders' => (int) $row->orders,
                'spend' => Money::of($row->spend),
                'last_order_at' => $row->last_order_at,
            ])->all(),
            'total' => $rows->count(),
            'repeat_rate' => $this->rate($rows->filter(fn (object $row): bool => (int) $row->orders > 1)->count(), $rows->count()),
            'average_spend' => $rows->count() > 0
                ? Money::of((float) $rows->sum('spend'))->multiply(1 / $rows->count())
                : Money::zero(),
        ];
    }

    public function products(DateRange $range): array
    {
        $shopId = $this->scope->shopId();

        $sold = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.shop_id', $shopId)
            ->whereBetween('orders.created_at', [$range->from, $range->to])
            ->whereIn('orders.order_status', AnalyticsService::revenueOrderStatuses())
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->selectRaw('order_items.product_id, order_items.product_name, SUM(order_items.quantity) as units, SUM(order_items.sub_total) as revenue, COUNT(DISTINCT orders.customer_id) as buyers')
            ->orderByDesc('revenue')
            ->limit(50)
            ->get();

        $catalogue = Product::query()
            ->where('shop_id', $shopId)
            ->get(['id', 'name', 'current_stock', 'low_stock_threshold', 'sold_count', 'status'])
            ->keyBy('id');

        $soldIds = $sold->pluck('product_id')->filter()->map('intval')->all();

        $unsold = $catalogue->reject(fn (Product $product): bool => in_array((int) $product->getKey(), $soldIds, true))->take(10);

        return [
            'range' => $range,
            'sold' => $sold->map(fn (object $row): array => [
                'id' => (int) $row->product_id,
                'name' => (string) $row->product_name,
                'units' => (int) $row->units,
                'buyers' => (int) $row->buyers,
                'revenue' => Money::of($row->revenue),
                'stock' => (int) ($catalogue[$row->product_id]->current_stock ?? 0),
            ])->all(),
            'unsold' => $unsold->map(fn (Product $product): array => [
                'id' => (int) $product->getKey(),
                'name' => (string) $product->name,
                'stock' => (int) $product->current_stock,
            ])->values()->all(),
            'catalogue' => $catalogue->count(),
            'low_stock' => $catalogue->filter(fn (Product $product): bool => (int) $product->current_stock <= (int) ($product->low_stock_threshold ?? 0))->count(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function series(int $shopId, DateRange $range): array
    {
        $days = min(90, $range->days());
        $start = $range->to->subDays($days - 1)->startOfDay();

        $rows = $this->revenue($shopId, DateRange::fromRequest(
            \Illuminate\Http\Request::create('/', 'GET', [
                'from' => $start->toDateString(),
                'to' => $range->to->toDateString(),
                'range' => 'custom',
            ])
        ))->get(['orders.created_at', 'orders.sub_total'])
            ->groupBy(fn (Order $order): string => $order->created_at->toDateString());

        $series = [];

        for ($day = 0; $day < $days; $day++) {
            $date = $start->addDays($day);
            $bucket = $rows->get($date->toDateString());

            $series[] = [
                'label' => $date->format('d M'),
                'revenue' => (float) ($bucket?->sum('sub_total') ?? 0),
                'orders' => (int) ($bucket?->count() ?? 0),
            ];
        }

        return $series;
    }

    /** @return list<array{status: string, label: string, total: int}> */
    private function statusMix(int $shopId, DateRange $range): array
    {
        $counts = Order::query()
            ->where('shop_id', $shopId)
            ->whereBetween('created_at', [$range->from, $range->to])
            ->selectRaw('order_status, COUNT(*) as aggregate')
            ->groupBy('order_status')
            ->pluck('aggregate', 'order_status');

        $out = [];

        foreach (\App\Enums\OrderStatus::cases() as $case) {
            $stored = $case->stored();
            $total = (int) ($counts[$stored] ?? 0);

            if ($total > 0) {
                $out[] = ['status' => $stored, 'label' => $case->label(), 'total' => $total];
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function topDays(int $shopId, DateRange $range): array
    {
        return DB::table('orders')
            ->where('shop_id', $shopId)
            ->whereBetween('created_at', [$range->from, $range->to])
            ->whereIn('order_status', AnalyticsService::revenueOrderStatuses())
            ->groupBy(DB::raw('DATE(created_at)'))
            ->selectRaw('DATE(created_at) as day, COUNT(*) as orders, SUM(sub_total) as revenue')
            ->orderByDesc('revenue')
            ->limit(7)
            ->get()
            ->map(fn (object $row): array => [
                'day' => (string) $row->day,
                'orders' => (int) $row->orders,
                'revenue' => Money::of($row->revenue),
            ])->all();
    }

    private function revenue(int $shopId, DateRange $range)
    {
        return Order::query()
            ->where('shop_id', $shopId)
            ->whereBetween('created_at', [$range->from, $range->to])
            ->whereIn('order_status', AnalyticsService::revenueOrderStatuses())
            ->whereIn('payment_status', AnalyticsService::paidPaymentStatuses());
    }

    private function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 2) : 0.0;
    }

    /** @return array{value: float, direction: string} */
    private function growth(float $current, float $previous): array
    {
        if ($previous == 0.0) {
            return ['value' => $current > 0 ? 100.0 : 0.0, 'direction' => $current > 0 ? 'up' : 'flat'];
        }

        $value = round((($current - $previous) / abs($previous)) * 100, 1);

        return [
            'value' => $value,
            'direction' => $value > 0.05 ? 'up' : ($value < -0.05 ? 'down' : 'flat'),
        ];
    }

    public function unsoldCount(int $shopId): int
    {
        return (int) Product::query()
            ->where('shop_id', $shopId)
            ->doesntHave('orderItems')
            ->count();
    }

    /**
     * Perbandingan periode berjalan vs periode sebelumnya.
     *
     * @return array{current: array{gross: Money, orders: int}, previous: array{gross: Money, orders: int}, delta_revenue: array, delta_orders: array}
     */
    public function compare(DateRange $range): array
    {
        $shopId = $this->scope->shopId();
        $previous = $range->previous();
        $currentGross = (float) $this->revenue($shopId, $range)->sum('sub_total');
        $currentOrders = (int) $this->revenue($shopId, $range)->count();
        $previousGross = (float) $this->revenue($shopId, $previous)->sum('sub_total');
        $previousOrders = (int) $this->revenue($shopId, $previous)->count();

        return [
            'current' => ['gross' => Money::of($currentGross), 'orders' => $currentOrders],
            'previous' => ['gross' => Money::of($previousGross), 'orders' => $previousOrders],
            'delta_revenue' => $this->growth($currentGross, $previousGross),
            'delta_orders' => $this->growth((float) $currentOrders, (float) $previousOrders),
        ];
    }

    /**
     * Funnel drop-off checkout khusus toko: kunjungan -> keranjang -> checkout -> bayar.
     * Sumber defensif: shop_visitors, carts, orders.
     *
     * @return array{steps: list<array{key: string, label: string, total: int}>, drop_off: list<array{from: string, to: string, rate: float}>}
     */
    public function funnel(DateRange $range): array
    {
        $shopId = $this->scope->shopId();
        $visitors = 0;
        try {
            $visitors = (int) DB::table('shop_visitors')->where('shop_id', $shopId)->whereBetween('visited_at', [$range->from, $range->to])->distinct()->count('visitor_key');
        } catch (\Throwable) {
            $visitors = 0;
        }
        $carts = 0;
        try {
            $carts = (int) DB::table('carts')->where('shop_id', $shopId)->whereBetween('created_at', [$range->from, $range->to])->count();
        } catch (\Throwable) {
            try {
                $carts = (int) DB::table('carts')->whereBetween('created_at', [$range->from, $range->to])->count();
            } catch (\Throwable) {
                $carts = 0;
            }
        }
        $orders = (int) Order::query()->where('shop_id', $shopId)->whereBetween('created_at', [$range->from, $range->to])->count();
        $paid = (int) $this->revenue($shopId, $range)->count();
        $steps = [
            ['key' => 'visitors', 'label' => 'Pengunjung', 'total' => $visitors],
            ['key' => 'carts', 'label' => 'Keranjang', 'total' => $carts],
            ['key' => 'orders', 'label' => 'Checkout', 'total' => $orders],
            ['key' => 'paid', 'label' => 'Terbayar', 'total' => $paid],
        ];
        $dropOff = [];
        for ($i = 0; $i < count($steps) - 1; $i++) {
            $from = $steps[$i]['total'];
            $to = $steps[$i + 1]['total'];
            $dropOff[] = ['from' => $steps[$i]['label'], 'to' => $steps[$i + 1]['label'], 'rate' => $from > 0 ? round((($from - $to) / $from) * 100, 1) : 0.0];
        }

        return ['steps' => $steps, 'drop_off' => $dropOff];
    }
}
