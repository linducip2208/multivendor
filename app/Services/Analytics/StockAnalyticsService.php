<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Stock health reporting: value on hand, coverage, dead stock and the
 * per-warehouse breakdown used by the inventory and stock-report screens.
 */
final class StockAnalyticsService extends AnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function report(DateRange $range, int $perPage = 20, int $page = 1, string $search = '', string $filter = ''): array
    {
        return $this->remember('stock', $range, function () use ($range, $perPage, $page, $search, $filter): array {
            $base = Product::query()->where('status', 'approved');

            if ($filter === 'out') {
                $base->where('current_stock', '<=', 0);
            } elseif ($filter === 'low') {
                $base->where('current_stock', '>', 0)->whereColumn('current_stock', '<=', 'low_stock_threshold');
            } elseif ($filter === 'healthy') {
                $base->whereColumn('current_stock', '>', 'low_stock_threshold');
            }

            if ($search !== '') {
                $base->where(function ($q) use ($search): void {
                    $q->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%');
                });
            }

            $perPage = max(5, min(100, $perPage));
            $page = max(1, $page);
            $total = (clone $base)->count();

            $sold = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereIn('orders.order_status', self::revenueOrderStatuses())
                ->whereBetween('orders.created_at', [$range->from, $range->to])
                ->where('order_items.product_id', '>', 0)
                ->groupBy('order_items.product_id')
                ->selectRaw('order_items.product_id, SUM(order_items.quantity) as sold')
                ->get()
                ->keyBy('product_id');

            $rows = $base->orderBy('current_stock')
                ->forPage($page, $perPage)
                ->get()
                ->map(function (Product $product) use ($sold): array {
                    $units = (int) ($sold[$product->id]->sold ?? 0);
                    $stock = (int) $product->current_stock;
                    $price = (float) $product->price;
                    $threshold = (int) $product->low_stock_threshold;

                    return [
                        'id' => (int) $product->id,
                        'name' => (string) $product->name,
                        'sku' => (string) ($product->sku ?? ''),
                        'shop' => (string) ($product->shop?->name ?? '-'),
                        'stock' => $stock,
                        'reserved' => 0,
                        'threshold' => $threshold,
                        'price' => $price,
                        'value' => $stock * $price,
                        'sold' => $units,
                        'cover_days' => $units > 0 ? (int) round($stock / max(1, $units) * 30) : null,
                        'state' => $stock <= 0 ? 'out_of_stock' : ($stock <= $threshold ? 'low_stock' : 'healthy'),
                        'url' => route('admin.products.show', $product->id),
                    ];
                })
                ->all();

            $totals = (clone $base)->get(['current_stock', 'price']);
            $value = 0.0;
            $units = 0;
            foreach ($totals as $product) {
                $value += (int) $product->current_stock * (float) $product->price;
                $units += (int) $product->current_stock;
            }

            $all = Product::query()->where('status', 'approved');

            return [
                'range' => $range->toArray(),
                'kpis' => [
                    'value' => ['value' => $value, 'label' => 'Nilai Stok', 'money' => true, 'trend' => $this->delta(0.0, 0.0)],
                    'units' => ['value' => $units, 'label' => 'Unit di Gudang', 'money' => false, 'trend' => $this->delta(0.0, 0.0)],
                    'out' => [
                        'value' => (int) (clone $all)->where('current_stock', '<=', 0)->count(),
                        'label' => 'Stok Habis',
                        'money' => false,
                        'trend' => $this->delta(0.0, 0.0),
                    ],
                    'low' => [
                        'value' => (int) (clone $all)->where('current_stock', '>', 0)->whereColumn('current_stock', '<=', 'low_stock_threshold')->count(),
                        'label' => 'Stok Menipis',
                        'money' => false,
                        'trend' => $this->delta(0.0, 0.0),
                    ],
                    'warehouses' => [
                        'value' => (int) Warehouse::query()->where('is_active', true)->count(),
                        'label' => 'Gudang Aktif',
                        'money' => false,
                        'trend' => $this->delta(0.0, 0.0),
                    ],
                ],
                'rows' => $rows,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => (int) max(1, (int) ceil($total / $perPage)),
                ],
                'by_warehouse' => $this->byWarehouse(),
                'movement_series' => $this->movementSeries($range),
            ];
        }, ['per_page' => $perPage, 'page' => $page, 'search' => $search, 'filter' => $filter]);
    }

    /**
     * @return list<array{id: int, name: string, code: string, city: string, skus: int, units: int, value: float}>
     */
    public function byWarehouse(): array
    {
        if (! $this->has('product_stocks')) {
            return [];
        }

        $rows = DB::table('product_stocks')
            ->join('warehouses', 'warehouses.id', '=', 'product_stocks.warehouse_id')
            ->leftJoin('products', 'products.id', '=', 'product_stocks.product_id')
            ->whereNull('warehouses.deleted_at')
            ->groupBy('warehouses.id', 'warehouses.name', 'warehouses.code', 'warehouses.city')
            ->selectRaw('warehouses.id, warehouses.name, warehouses.code, warehouses.city, COUNT(product_stocks.id) as skus, SUM(product_stocks.on_hand) as units, COALESCE(SUM(product_stocks.on_hand * products.price), 0) as value')
            ->orderBy('warehouses.name')
            ->get();

        return $rows->map(fn ($row): array => [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'code' => (string) $row->code,
            'city' => (string) ($row->city ?? '-'),
            'skus' => (int) $row->skus,
            'units' => (int) $row->units,
            'value' => (float) $row->value,
        ])->all();
    }

    /**
     * @return array{labels: list<string>, in: list<float>, out: list<float>}
     */
    private function movementSeries(DateRange $range): array
    {
        if (! $this->has('stock_movements')) {
            return ['labels' => $range->labels(), 'in' => [], 'out' => []];
        }

        $expression = match (DB::connection()->getDriverName()) {
            'sqlite' => 'date(created_at)',
            'pgsql' => "to_char(created_at, 'YYYY-MM-DD')",
            default => 'DATE(created_at)',
        };

        $rows = DB::table('stock_movements')
            ->whereBetween('created_at', [$range->from, $range->to])
            ->selectRaw($expression.' as bucket, type, SUM(ABS(quantity)) as units')
            ->groupBy('bucket', 'type')
            ->get();

        $in = [];
        $out = [];
        foreach ($rows as $row) {
            $bucket = (string) $row->bucket;
            $in[$bucket] = ($in[$bucket] ?? 0) + (int) $row->units;
            $out[$bucket] = ($out[$bucket] ?? 0) + (int) $row->units;
        }

        $labels = $range->labels();

        return [
            'labels' => $labels,
            'in' => $this->densify($labels, $in, 'units'),
            'out' => $this->densify($labels, $out, 'units'),
        ];
    }
}
