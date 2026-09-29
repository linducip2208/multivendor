<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

/**
 * Catalogue performance: best sellers, dead stock, rating distribution and
 * category/brand concentration.
 */
final class ProductAnalyticsService extends AnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function report(DateRange $range, int $perPage = 20, int $page = 1, string $search = '', string $sort = 'revenue'): array
    {
        return $this->remember('products', $range, function () use ($range, $perPage, $page, $search, $sort): array {
            $rows = $this->ranking($range, $sort, $search, $perPage, $page);
            $all = $this->ranking($range, 'revenue', '', 500, 1);

            $revenue = array_sum(array_map(fn (array $r): float => (float) $r['revenue'], $all));
            $units = array_sum(array_map(fn (array $r): float => (float) $r['units'], $all));

            $approved = (int) Product::query()->where('status', 'approved')->count();
            $outOfStock = (int) Product::query()->where('status', 'approved')->where('current_stock', '<=', 0)->count();
            $lowStock = (int) Product::query()->where('status', 'approved')
                ->where('current_stock', '>', 0)
                ->whereColumn('current_stock', '<=', 'low_stock_threshold')
                ->count();

            $top = array_slice($all, 0, 10);
            $topRevenue = array_sum(array_map(fn (array $r): float => (float) $r['revenue'], $top));
            $topUnits = array_sum(array_map(fn (array $r): float => (float) $r['units'], $top));

            return [
                'range' => $range->toArray(),
                'kpis' => [
                    'products' => ['value' => $approved, 'label' => 'Produk Aktif', 'money' => false, 'trend' => $this->delta(0.0, 0.0)],
                    'revenue' => ['value' => $revenue, 'label' => 'Omzet Produk', 'money' => true, 'trend' => $this->delta(0.0, 0.0)],
                    'units' => ['value' => $units, 'label' => 'Unit Terjual', 'money' => false, 'trend' => $this->delta(0.0, 0.0)],
                    'out_of_stock' => ['value' => $outOfStock, 'label' => 'Stok Habis', 'money' => false, 'trend' => $this->delta(0.0, 0.0)],
                    'low_stock' => ['value' => $lowStock, 'label' => 'Stok Menipis', 'money' => false, 'trend' => $this->delta(0.0, 0.0)],
                    'concentration' => [
                        'value' => $revenue > 0 ? round(($topRevenue / $revenue) * 100, 1) : 0.0,
                        'label' => 'Konsentrasi 10 Teratas',
                        'money' => false,
                        'hint' => 'Porsi omzet dari 10 produk teratas',
                    ],
                ],
                'rows' => $rows,
                'pagination' => $this->pagination($range, $sort, $search, $perPage, $page),
                'top_labels' => array_column($top, 'name'),
                'top_revenue' => array_map(fn (array $r): float => (float) $r['revenue'], $top),
                'top_units' => array_map(fn (array $r): float => (float) $r['units'], $top),
                'top_concentration' => $topUnits > 0 ? round(($topUnits / max(1, $units)) * 100, 1) : 0.0,
                'categories' => $this->categoryBreakdown($range),
                'brands' => $this->brandBreakdown($range),
                'ratings' => $this->ratingDistribution(),
            ];
        }, ['per_page' => $perPage, 'page' => $page, 'search' => $search, 'sort' => $sort]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ranking(DateRange $range, string $sort, string $search, int $perPage, int $page): array
    {
        $query = Product::query()
            ->leftJoin('order_items', 'order_items.product_id', '=', 'products.id')
            ->leftJoin('orders', function ($join) use ($range): void {
                $join->on('orders.id', '=', 'order_items.order_id')
                    ->whereIn('orders.order_status', self::revenueOrderStatuses())
                    ->whereIn('orders.payment_status', self::paidPaymentStatuses())
                    ->whereBetween('orders.created_at', [$range->from, $range->to]);
            })
            ->select('products.id', 'products.name', 'products.sku', 'products.price', 'products.current_stock', 'products.rating_average', 'products.shop_id', 'products.category_id', 'products.brand_id')
            ->selectRaw('COALESCE(SUM(order_items.sub_total), 0) as revenue')
            ->selectRaw('COALESCE(SUM(CASE WHEN orders.id IS NULL THEN 0 ELSE order_items.quantity END), 0) as units')
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.price', 'products.current_stock', 'products.rating_average', 'products.shop_id', 'products.category_id', 'products.brand_id');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('products.name', 'like', '%'.$search.'%')
                    ->orWhere('products.sku', 'like', '%'.$search.'%');
            });
        }

        $orderColumn = match ($sort) {
            'units' => 'units',
            'stock' => 'products.current_stock',
            'rating' => 'products.rating_average',
            default => 'revenue',
        };

        $rows = $query->orderByDesc($orderColumn)
            ->forPage(max(1, $page), max(5, min(100, $perPage)))
            ->get();

        $shopIds = $rows->pluck('shop_id')->filter()->map(fn ($id): int => (int) $id)->unique()->all();
        $categoryIds = $rows->pluck('category_id')->filter()->map(fn ($id): int => (int) $id)->unique()->all();
        $brandIds = $rows->pluck('brand_id')->filter()->map(fn ($id): int => (int) $id)->unique()->all();

        $shops = $shopIds === [] ? [] : Shop::query()->whereIn('id', $shopIds)->pluck('name', 'id')->all();
        $categories = $categoryIds === [] ? [] : Category::query()->whereIn('id', $categoryIds)->pluck('name', 'id')->all();
        $brands = $brandIds === [] ? [] : Brand::query()->whereIn('id', $brandIds)->pluck('name', 'id')->all();

        return $rows->map(fn (Product $product): array => [
            'id' => (int) $product->id,
            'name' => (string) $product->name,
            'sku' => (string) ($product->sku ?? ''),
            'price' => (float) $product->price,
            'stock' => (int) $product->current_stock,
            'rating' => (float) $product->rating_average,
            'shop' => (string) ($shops[(int) $product->shop_id] ?? '-'),
            'category' => (string) ($categories[(int) $product->category_id] ?? '-'),
            'brand' => (string) ($brands[(int) $product->brand_id] ?? '-'),
            'revenue' => (float) ($product->getAttribute('revenue') ?? 0),
            'units' => (int) ($product->getAttribute('units') ?? 0),
            'url' => route('admin.products.show', $product->id),
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function pagination(DateRange $range, string $sort, string $search, int $perPage, int $page): array
    {
        $query = Product::query();
        if ($search !== '') {
            $query->where(fn ($b) => $b->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%'));
        }
        $total = (int) $query->count();
        $perPage = max(5, min(100, $perPage));

        return [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => max(1, $page),
            'last_page' => (int) max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * @return list<array{name: string, revenue: float, products: int}>
     */
    private function categoryBreakdown(DateRange $range): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('orders.order_status', self::revenueOrderStatuses())
            ->whereIn('orders.payment_status', self::paidPaymentStatuses())
            ->whereBetween('orders.created_at', [$range->from, $range->to])
            ->whereNotNull('products.category_id')
            ->groupBy('products.category_id')
            ->selectRaw('products.category_id as category_id, SUM(order_items.sub_total) as revenue, COUNT(DISTINCT products.id) as products')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        $ids = $rows->pluck('category_id')->map(fn ($id): int => (int) $id)->all();
        $names = $ids === [] ? [] : Category::query()->whereIn('id', $ids)->pluck('name', 'id')->all();

        return $rows->map(fn ($row): array => [
            'name' => (string) ($names[(int) $row->category_id] ?? '#'.$row->category_id),
            'revenue' => (float) $row->revenue,
            'products' => (int) $row->products,
        ])->all();
    }

    /**
     * @return list<array{name: string, revenue: float, products: int}>
     */
    private function brandBreakdown(DateRange $range): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('orders.order_status', self::revenueOrderStatuses())
            ->whereIn('orders.payment_status', self::paidPaymentStatuses())
            ->whereBetween('orders.created_at', [$range->from, $range->to])
            ->whereNotNull('products.brand_id')
            ->groupBy('products.brand_id')
            ->selectRaw('products.brand_id as brand_id, SUM(order_items.sub_total) as revenue, COUNT(DISTINCT products.id) as products')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        $ids = $rows->pluck('brand_id')->map(fn ($id): int => (int) $id)->all();
        $names = $ids === [] ? [] : Brand::query()->whereIn('id', $ids)->pluck('name', 'id')->all();

        return $rows->map(fn ($row): array => [
            'name' => (string) ($names[(int) $row->brand_id] ?? '#'.$row->brand_id),
            'revenue' => (float) $row->revenue,
            'products' => (int) $row->products,
        ])->all();
    }

    /**
     * @return list<array{bucket: int, count: int}>
     */
    private function ratingDistribution(): array
    {
        $out = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        try {
            Product::query()
                ->where('status', 'approved')
                ->where('rating_count', '>', 0)
                ->get(['rating_average'])
                ->each(function (Product $product) use (&$out): void {
                    $star = (int) floor((float) $product->rating_average);
                    $star = max(1, min(5, $star));
                    $out[$star]++;
                });
        } catch (\Throwable) {
            return array_map(fn (int $count): array => ['bucket' => 0, 'count' => $count], $out);
        }

        $rows = [];
        for ($star = 5; $star >= 1; $star--) {
            $rows[] = ['bucket' => $star, 'count' => $out[$star]];
        }

        return $rows;
    }
}
