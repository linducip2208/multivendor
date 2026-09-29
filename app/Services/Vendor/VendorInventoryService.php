<?php

declare(strict_types=1);

namespace App\Services\Vendor;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Services\Analytics\AnalyticsService;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Inventory reads and stock movements.
 *
 * Every adjustment is a money-adjacent mutation: it locks the product row,
 * refuses to drive stock negative, writes the movement ledger and records the
 * balance that resulted so a recount is always explainable.
 */
final class VendorInventoryService
{
    public function __construct(private readonly VendorScope $scope) {}

    public function overview(string $search = '', string $stock = ''): array
    {
        $shopId = $this->scope->shopId();

        $query = Product::query()
            ->where('shop_id', $shopId)
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('sku', 'like', '%'.$search.'%')
                ->orWhere('barcode', 'like', '%'.$search.'%')))
            ->when($stock === 'low', fn ($q) => $q->whereColumn('current_stock', '<=', DB::raw('COALESCE(low_stock_threshold, 0)')))
            ->when($stock === 'out', fn ($q) => $q->where('current_stock', '<=', 0))
            ->when($stock === 'in', fn ($q) => $q->where('current_stock', '>', DB::raw('COALESCE(low_stock_threshold, 0)')));

        $products = $query->orderBy('current_stock')->orderBy('name')->paginate(20)->withQueryString();

        return [
            'products' => $products,
            'search' => $search,
            'stock' => $stock,
            'stats' => $this->stats($shopId),
        ];
    }

    /** @return array<string, int|float> */
    public function stats(int $shopId): array
    {
        $row = Product::query()
            ->where('shop_id', $shopId)
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(current_stock), 0) as units')
            ->selectRaw('SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END) as out_of_stock')
            ->selectRaw('SUM(CASE WHEN current_stock > 0 AND current_stock <= COALESCE(low_stock_threshold, 0) THEN 1 ELSE 0 END) as low_stock')
            ->selectRaw('COALESCE(SUM(current_stock * price), 0) as value')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'units' => (int) ($row->units ?? 0),
            'out_of_stock' => (int) ($row->out_of_stock ?? 0),
            'low_stock' => (int) ($row->low_stock ?? 0),
            'value' => round((float) ($row->value ?? 0), 2),
        ];
    }

    public function movements(array $filters = [], int $perPage = 25)
    {
        $shopId = $this->scope->shopId();

        return StockMovement::query()
            ->whereHas('product', fn ($query) => $query->where('shop_id', $shopId))
            ->when($filters['product_id'] ?? null, fn ($q, $id) => $q->where('product_id', (int) $id))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->with(['product:id,name,sku', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function adjust(Product $product, int $delta, string $type, string $note): StockMovement
    {
        abort_if((int) $product->shop_id !== $this->scope->shopId(), 403);

        return DB::transaction(function () use ($product, $delta, $type, $note): StockMovement {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->getKey());

            $available = (int) $locked->current_stock;

            if ($delta < 0 && abs($delta) > $available) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stok tidak cukup. Tersedia '.$available.' unit.',
                ]);
            }

            $locked->increment('current_stock', $delta);

            $movement = StockMovement::query()->create([
                'warehouse_id' => null,
                'product_id' => $locked->getKey(),
                'product_variant_id' => null,
                'type' => in_array($type, ['in', 'out', 'adjustment'], true) ? $type : 'adjustment',
                'quantity' => abs($delta),
                'balance_after' => $available + $delta,
                'reference_type' => 'manual',
                'note' => VendorScope::clean($note, 255),
                'created_by' => $this->scope->userId(),
            ]);

            app(AuditLogger::class)->log('vendor.stock.adjusted', $locked, [
                'current_stock' => $available,
            ], [
                'current_stock' => $available + $delta,
                'delta' => $delta,
                'reason' => $type,
            ], $this->scope->userId());

            return $movement->refresh();
        }, 3);
    }

    /** @return array<string, mixed> */
    public function detail(Product $product): array
    {
        abort_if((int) $product->shop_id !== $this->scope->shopId(), 403);

        $product->load(['variants:id,product_id,name,price,stock,sku']);

        $warehouses = [];

        if (Schema::hasTable('product_stocks')) {
            $warehouses = ProductStock::query()
                ->where('product_id', $product->getKey())
                ->with('warehouse:id,name,code')
                ->get();
        }

        return [
            'product' => $product,
            'warehouses' => $warehouses,
            'movements' => $this->movements(['product_id' => (int) $product->getKey()], 15),
            'sold' => $this->soldUnits($product),
            'revenue' => $this->revenue($product),
        ];
    }

    public function soldUnits(Product $product): int
    {
        return (int) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.shop_id', $this->scope->shopId())
            ->where('order_items.product_id', $product->getKey())
            ->whereIn('orders.order_status', AnalyticsService::revenueOrderStatuses())
            ->sum('order_items.quantity');
    }

    public function revenue(Product $product): Money
    {
        $value = (float) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.shop_id', $this->scope->shopId())
            ->where('order_items.product_id', $product->getKey())
            ->whereIn('orders.order_status', AnalyticsService::revenueOrderStatuses())
            ->sum('order_items.sub_total');

        return Money::of($value);
    }
}
