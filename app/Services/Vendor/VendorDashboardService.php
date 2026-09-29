<?php

declare(strict_types=1);

namespace App\Services\Vendor;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Models\VendorWithdrawRequest;
use App\Services\Analytics\AnalyticsService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every number the vendor dashboard shows.
 *
 * The controller owns no query: it asks this service for a single, already
 * scoped snapshot so a full page render is one deterministic read.
 */
final class VendorDashboardService
{
    private const TREND_DAYS = 30;

    public function __construct(private readonly VendorScope $scope) {}

    public function summary(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $shopId = $this->scope->shopId();
        $to ??= CarbonImmutable::now()->endOfDay();
        $from ??= $to->subDays(29)->startOfDay();

        return [
            'shop' => $this->scope->shop(),
            'range' => ['from' => $from, 'to' => $to, 'days' => $from->diffInDays($to) + 1],
            'kpi' => $this->kpi($shopId, $from, $to),
            'trend' => $this->trend($shopId),
            'order_status' => $this->orderStatusBreakdown($shopId),
            'best_products' => $this->bestProducts($shopId, $from, $to),
            'low_stock' => $this->lowStock($shopId),
            'pending_fulfillment' => $this->pendingFulfillment($shopId),
            'pending_payouts' => $this->pendingPayouts($shopId),
            'reviews' => $this->reviews($shopId),
            'campaigns' => $this->campaignPerformance($shopId, $from, $to),
            'recent_orders' => $this->recentOrders($shopId),
            'top_customers' => $this->topCustomers($shopId, $from, $to),
            'completeness' => $this->completeness($this->scope->shop()),
            'performance' => $this->performanceBadges($shopId),
        ];
    }

    public function kpi(int $shopId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $revenue = $this->revenueQuery($shopId, $from, $to);
        $orders = $this->ordersQuery($shopId, $from, $to);
        $previous = $this->previousWindow($from, $to);

        $gross = (float) (clone $revenue)->sum('sub_total');
        $tax = (float) (clone $revenue)->sum('tax');
        $discount = (float) (clone $revenue)->sum('discount');
        $commission = (float) (clone $revenue)->sum('admin_commission');
        $orderCount = (int) (clone $orders)->count();
        $net = Money::of($gross)->add($tax)->subtract($discount)->subtract($commission)->maxZero();

        $previousGross = (float) $this->revenueQuery($shopId, $previous[0], $previous[1])->sum('sub_total');
        $previousOrders = (int) $this->ordersQuery($shopId, $previous[0], $previous[1])->count();

        $visitors = $this->visitorCount($shopId, $from, $to);
        $previousVisitors = $this->visitorCount($shopId, $previous[0], $previous[1]);

        return [
            'gross_revenue' => Money::of($gross),
            'net_revenue' => $net,
            'tax' => Money::of($tax),
            'discount' => Money::of($discount),
            'commission' => Money::of($commission),
            'orders' => $orderCount,
            'aov' => $orderCount > 0 ? Money::of($gross)->multiply(1 / $orderCount) : Money::zero(),
            'visitors' => $visitors,
            'conversion' => $this->rate($orderCount, $visitors),
            'revenue_delta' => $this->delta($gross, $previousGross),
            'order_delta' => $this->delta((float) $orderCount, (float) $previousOrders),
            'visitor_delta' => $this->delta((float) $visitors, (float) $previousVisitors),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function trend(int $shopId, int $days = self::TREND_DAYS): array
    {
        $start = CarbonImmutable::now()->subDays($days - 1)->startOfDay();
        $end = CarbonImmutable::now()->endOfDay();

        $rows = $this->revenueQuery($shopId, $start, $end)
            ->get(['orders.created_at', 'orders.sub_total', 'orders.total'])
            ->groupBy(fn (Order $order): string => $order->created_at->toDateString());

        $labels = [];
        $revenue = [];
        $orders = [];

        for ($day = 0; $day < $days; $day++) {
            $date = $start->addDays($day);
            $key = $date->toDateString();
            $bucket = $rows->get($key);

            $labels[] = $date->format('d M');
            $revenue[] = (float) ($bucket?->sum('sub_total') ?? 0);
            $orders[] = (int) ($bucket?->count() ?? 0);
        }

        return compact('labels', 'revenue', 'orders');
    }

    /** @return list<array{status: string, label: string, badge: string, total: int}> */
    public function orderStatusBreakdown(int $shopId): array
    {
        $counts = Order::query()
            ->where('shop_id', $shopId)
            ->where('created_at', '>=', CarbonImmutable::now()->subDays(self::TREND_DAYS)->startOfDay())
            ->selectRaw('order_status, COUNT(*) as aggregate')
            ->groupBy('order_status')
            ->pluck('aggregate', 'order_status');

        $out = [];

        foreach (OrderStatus::cases() as $case) {
            $stored = $case->stored();
            $total = (int) ($counts[$stored] ?? 0);

            if ($total === 0) {
                continue;
            }

            $out[] = [
                'status' => $stored,
                'label' => $case->label(),
                'badge' => $case->badge(),
                'total' => $total,
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function bestProducts(int $shopId, CarbonImmutable $from, CarbonImmutable $to, int $limit = 8): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.shop_id', $shopId)
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereIn('orders.order_status', AnalyticsService::revenueOrderStatuses())
            ->whereIn('orders.payment_status', AnalyticsService::paidPaymentStatuses())
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->selectRaw('order_items.product_id, order_items.product_name, SUM(order_items.quantity) as units, SUM(order_items.sub_total) as revenue')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        $thumbnails = Product::query()
            ->where('shop_id', $shopId)
            ->whereIn('id', $rows->pluck('product_id')->filter()->all())
            ->pluck('thumbnail', 'id');

        return $rows->map(fn (object $row): array => [
            'product_id' => (int) $row->product_id,
            'name' => (string) $row->product_name,
            'thumbnail' => $thumbnails[$row->product_id] ?? null,
            'units' => (int) $row->units,
            'revenue' => Money::of($row->revenue),
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    public function lowStock(int $shopId, int $limit = 10): array
    {
        return Product::query()
            ->where('shop_id', $shopId)
            ->where('product_type', 'physical')
            ->whereNotIn('status', ['suspended'])
            ->whereColumn('current_stock', '<=', DB::raw('LOWEST(COALESCE(' . DB::getQueryGrammar()->wrap('low_stock_threshold') . ', 0) + 1)'))
            ->orderBy('current_stock')
            ->limit($limit)
            ->get(['id', 'name', 'sku', 'current_stock', 'low_stock_threshold'])
            ->map(fn (Product $product): array => [
                'id' => (int) $product->getKey(),
                'name' => (string) $product->name,
                'sku' => $product->sku,
                'stock' => (int) $product->current_stock,
                'threshold' => (int) ($product->low_stock_threshold ?? 0),
                'url' => route('vendor.products.edit', $product),
            ])->all();
    }

    /** @return array{total: int, items: Collection<int, Order>} */
    public function pendingFulfillment(int $shopId, int $limit = 10): array
    {
        $query = $this->ordersQuery($shopId, CarbonImmutable::now()->startOfYear(), CarbonImmutable::now()->endOfYear())
            ->whereIn('order_status', [
                OrderStatus::Paid->stored(),
                OrderStatus::Confirmed->stored(),
                OrderStatus::Processing->stored(),
                OrderStatus::Packed->stored(),
            ])
            ->where('fulfillment_status', '!=', 'fulfilled');

        $total = (int) (clone $query)->count();

        return [
            'total' => $total,
            'items' => $query->with('customer:id,name')->orderBy('created_at')->limit($limit)->get(),
        ];
    }

    /** @return array{amount: \App\Support\Money, total: int, items: Collection<int, VendorWithdrawRequest>} */
    public function pendingPayouts(int $shopId, int $limit = 6): array
    {
        $query = VendorWithdrawRequest::query()
            ->where('shop_id', $shopId)
            ->whereIn('status', ['pending', 'processing']);

        $rows = $query->orderByDesc('created_at')->limit($limit)->get();
        $total = (int) (clone $query)->count();

        return [
            'amount' => Money::sum($rows->pluck('amount')),
            'total' => $total,
            'items' => $rows,
        ];
    }

    /** @return array{average: float, total: int, pending: int, latest: Collection<int, ProductReview>} */
    public function reviews(int $shopId, int $limit = 5): array
    {
        $base = ProductReview::query()
            ->where('shop_id', $shopId)
            ->where('status', 'active');

        return [
            'average' => round((float) (clone $base)->avg('rating'), 2),
            'total' => (int) (clone $base)->count(),
            'pending' => (int) ProductReview::query()
                ->where('shop_id', $shopId)
                ->where('status', 'pending')
                ->count(),
            'latest' => (clone $base)->with('product:id,name', 'customer:id,name')
                ->orderByDesc('created_at')->limit($limit)->get(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function campaignPerformance(int $shopId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! DB::getSchemaBuilder()->hasTable('campaigns')) {
            return [];
        }

        return DB::table('campaigns')
            ->where(function ($query) use ($shopId): void {
                $query->whereNull('shop_id')->orWhere('shop_id', $shopId);
            })
            ->where('status', 'active')
            ->orderByDesc('ends_at')
            ->limit(6)
            ->get()
            ->map(function (object $campaign) use ($from, $to): array {
                $rules = json_decode((string) $campaign->rules, true);
                $type = is_array($rules) ? (string) ($rules['type'] ?? 'promo') : 'promo';
                $attributed = DB::table('orders')
                    ->where('shop_id', $campaign->shop_id)
                    ->whereBetween('created_at', [$from, $to])
                    ->where('coupon_code', (string) $campaign->code)
                    ->whereIn('order_status', AnalyticsService::revenueOrderStatuses())
                    ->whereIn('payment_status', AnalyticsService::paidPaymentStatuses());

                return [
                    'id' => (int) $campaign->id,
                    'name' => (string) $campaign->name,
                    'code' => (string) $campaign->code,
                    'type' => $type,
                    'ends_at' => $campaign->ends_at,
                    'orders' => (int) (clone $attributed)->count(),
                    'revenue' => Money::of((float) (clone $attributed)->sum('sub_total')),
                ];
            })
            ->all();
    }

    /** @return Collection<int, Order> */
    public function recentOrders(int $shopId, int $limit = 8): Collection
    {
        return $this->ordersQuery($shopId, CarbonImmutable::now()->subDays(29)->startOfDay(), CarbonImmutable::now()->endOfDay())
            ->with('customer:id,name')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /** @return list<array<string, mixed>> */
    public function topCustomers(int $shopId, CarbonImmutable $from, CarbonImmutable $to, int $limit = 5): array
    {
        $rows = $this->ordersQuery($shopId, $from, $to)
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) as orders, SUM(sub_total) as spend')
            ->orderByDesc('spend')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $customers = User::query()
            ->whereIn('id', $rows->pluck('customer_id')->all())
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        return $rows->map(function (object $row) use ($customers): array {
            $customer = $customers[$row->customer_id] ?? null;

            return [
                'id' => (int) $row->customer_id,
                'name' => (string) ($customer->name ?? 'Pelanggan #'.$row->customer_id),
                'email' => $customer?->email,
                'orders' => (int) $row->orders,
                'spend' => Money::of($row->spend),
            ];
        })->all();
    }

    /**
     * Skor kelengkapan toko (0-100) + rincian checklist.
     * Murni dari data toko yang sudah ada: profil, logo, produk, ulasan.
     *
     * @return array{score: int, label: string, badge: string, items: list<array{key: string, label: string, done: bool}>}
     */
    public function completeness(\App\Models\Shop $shop): array
    {
        $shopId = (int) $shop->getKey();
        $items = [
            ['key' => 'nama', 'label' => 'Nama & deskripsi toko', 'done' => trim((string) $shop->name) !== '' && trim((string) ($shop->description ?? '')) !== ''],
            ['key' => 'logo', 'label' => 'Logo toko', 'done' => trim((string) ($shop->logo ?? '')) !== ''],
            ['key' => 'banner', 'label' => 'Banner toko', 'done' => trim((string) ($shop->banner ?? '')) !== ''],
            ['key' => 'kontak', 'label' => 'Kontak (telepon/alamat)', 'done' => trim((string) ($shop->phone ?? '')) !== '' || trim((string) ($shop->address ?? '')) !== ''],
            ['key' => 'produk', 'label' => 'Minimal 1 produk aktif', 'done' => Product::query()->where('shop_id', $shopId)->where('status', 'approved')->exists()],
            ['key' => 'pengiriman', 'label' => 'Metode pengiriman aktif', 'done' => $this->hasShipping($shopId)],
        ];
        $done = count(array_filter($items, fn (array $i): bool => $i['done']));
        $score = (int) round(($done / max(1, count($items))) * 100);

        return [
            'score' => $score,
            'label' => $score >= 85 ? 'Lengkap' : ($score >= 60 ? 'Cukup' : 'Belum lengkap'),
            'badge' => $score >= 85 ? 'success' : ($score >= 60 ? 'warning' : 'danger'),
            'items' => $items,
        ];
    }

    /**
     * Badge performa: respons chat, rating, ketepatan pemenuhan.
     *
     * @return array{response_rate: float, response_badge: string, rating: float, rating_badge: string, fulfillment_rate: float}
     */
    public function performanceBadges(int $shopId): array
    {
        $since = CarbonImmutable::now()->subDays(30);
        $responseRate = 0.0;
        try {
            $threads = (int) DB::table('conversations')->where('shop_id', $shopId)->where('created_at', '>=', $since)->count();
            $replied = (int) DB::table('conversations')->where('shop_id', $shopId)->where('created_at', '>=', $since)->whereNotNull('first_reply_at')->count();
            $responseRate = $threads > 0 ? round(($replied / $threads) * 100, 1) : 100.0;
        } catch (\Throwable) {
            $responseRate = 0.0;
        }
        $reviews = $this->reviews($shopId);
        $rating = (float) ($reviews['average'] ?? 0.0);
        $fulfillment = 0.0;
        try {
            $total = (int) Order::query()->where('shop_id', $shopId)->where('created_at', '>=', $since)->count();
            $done = (int) Order::query()->where('shop_id', $shopId)->where('created_at', '>=', $since)->where('fulfillment_status', 'fulfilled')->count();
            $fulfillment = $total > 0 ? round(($done / $total) * 100, 1) : 100.0;
        } catch (\Throwable) {
            $fulfillment = 0.0;
        }

        return [
            'response_rate' => $responseRate,
            'response_badge' => $responseRate >= 80 ? 'success' : ($responseRate >= 50 ? 'warning' : 'danger'),
            'rating' => $rating,
            'rating_badge' => $rating >= 4.5 ? 'success' : ($rating >= 4.0 ? 'info' : ($rating >= 3.0 ? 'warning' : 'danger')),
            'fulfillment_rate' => $fulfillment,
        ];
    }

    private function hasShipping(int $shopId): bool
    {
        try {
            return (int) DB::table('shop_shipping_methods')->where('shop_id', $shopId)->where('is_active', true)->count() > 0;
        } catch (\Throwable) {
            try {
                return (int) DB::table('shop_shipping_method')->where('shop_id', $shopId)->count() > 0;
            } catch (\Throwable) {
                return true;
            }
        }
    }

    public function revenueQuery(int $shopId, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Order::query()
            ->where('shop_id', $shopId)
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('order_status', AnalyticsService::revenueOrderStatuses())
            ->whereIn('payment_status', AnalyticsService::paidPaymentStatuses());
    }

    public function ordersQuery(int $shopId, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Order::query()
            ->where('shop_id', $shopId)
            ->whereBetween('created_at', [$from, $to]);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function previousWindow(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $span = $to->diffInDays($from) + 1;

        return [$from->subDays($span)->startOfDay(), $to->subDays($span)->endOfDay()];
    }

    private function visitorCount(int $shopId, CarbonImmutable $from, CarbonImmutable $to): int
    {
        try {
            return (int) DB::table('shop_visitors')
                ->where('shop_id', $shopId)
                ->whereBetween('visited_at', [$from, $to])
                ->distinct()
                ->count('visitor_key');
        } catch (\Throwable) {
            return 0;
        }
    }

    private function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 2) : 0.0;
    }

    /** @return array{value: float, direction: string} */
    private function delta(float $current, float $previous): array
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
}
