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

    /**
     * Transfer gudang: buat pengajuan draft memakai tabel existing.
     *
     * @param  list<array{product_id:int,product_variant_id?:int|null,quantity:int}>  $items
     */
    public function requestTransfer(int $fromWarehouseId, int $toWarehouseId, array $items, ?string $note = null): \App\Models\StockTransfer
    {
        if ($fromWarehouseId === $toWarehouseId) {
            throw ValidationException::withMessages(['gudang' => 'Gudang asal dan tujuan tidak boleh sama.']);
        }

        $clean = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $qty = (int) ($item['quantity'] ?? 0);
            $pid = (int) ($item['product_id'] ?? 0);

            if ($pid <= 0 || $qty <= 0) {
                continue;
            }

            $clean[] = [
                'product_id' => $pid,
                'product_variant_id' => isset($item['product_variant_id']) ? (int) $item['product_variant_id'] : null,
                'quantity' => $qty,
            ];
        }

        if ($clean === []) {
            throw ValidationException::withMessages(['items' => 'Tambahkan minimal satu produk untuk transfer.']);
        }

        return DB::transaction(function () use ($fromWarehouseId, $toWarehouseId, $clean, $note): \App\Models\StockTransfer {
            do {
                $number = 'TRF-'.now()->format('Ymd').'-'.strtoupper(\Illuminate\Support\Str::random(8));
            } while (\App\Models\StockTransfer::where('transfer_number', $number)->exists());

            $transfer = \App\Models\StockTransfer::query()->create([
                'transfer_number' => $number,
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'status' => 'draft',
                'note' => $note !== null ? VendorScope::clean($note, 500) : null,
                'created_by' => $this->scope->userId(),
            ]);

            foreach ($clean as $item) {
                $transfer->items()->create([
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'quantity' => $item['quantity'],
                    'received_quantity' => 0,
                ]);
            }

            return $transfer->refresh(['items']);
        }, 3);
    }

    /** Approval transfer: draft -> in_transit (lock + catat kirim). */
    public function approveTransfer(\App\Models\StockTransfer $transfer): \App\Models\StockTransfer
    {
        return DB::transaction(function () use ($transfer): \App\Models\StockTransfer {
            $locked = \App\Models\StockTransfer::query()->lockForUpdate()->findOrFail($transfer->getKey());

            abort_if(! $locked->canShip(), 422, 'Transfer hanya bisa disetujui dari status draft.');

            $locked->forceFill(['status' => 'in_transit', 'shipped_at' => now()])->save();

            foreach ($locked->items()->lockForUpdate()->get() as $item) {
                StockMovement::query()->create([
                    'warehouse_id' => $locked->from_warehouse_id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'type' => 'transfer',
                    'quantity' => (int) $item->quantity,
                    'balance_after' => null,
                    'reference_type' => 'transfer',
                    'reference_id' => $locked->getKey(),
                    'note' => 'Transfer keluar '.$locked->transfer_number,
                    'created_by' => $this->scope->userId(),
                ]);
            }

            return $locked->fresh(['items']);
        }, 3);
    }

    /** Penerimaan transfer: in_transit -> received + stok tujuan bertambah. */
    public function receiveTransfer(\App\Models\StockTransfer $transfer): \App\Models\StockTransfer
    {
        return DB::transaction(function () use ($transfer): \App\Models\StockTransfer {
            $locked = \App\Models\StockTransfer::query()->lockForUpdate()->findOrFail($transfer->getKey());

            abort_if(! $locked->canReceive(), 422, 'Transfer hanya bisa diterima dari status dalam perjalanan.');

            foreach ($locked->items()->lockForUpdate()->get() as $item) {
                $item->forceFill(['received_quantity' => (int) $item->quantity])->save();

                StockMovement::query()->create([
                    'warehouse_id' => $locked->to_warehouse_id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'type' => 'transfer',
                    'quantity' => (int) $item->quantity,
                    'balance_after' => null,
                    'reference_type' => 'transfer',
                    'reference_id' => $locked->getKey(),
                    'note' => 'Transfer masuk '.$locked->transfer_number,
                    'created_by' => $this->scope->userId(),
                ]);

                $stock = \App\Models\ProductStock::query()
                    ->where('warehouse_id', $locked->to_warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->when($item->product_variant_id !== null, fn ($q) => $q->where('product_variant_id', $item->product_variant_id))
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    $stock->forceFill([
                        'on_hand' => (int) $stock->on_hand + (int) $item->quantity,
                        'last_counted_at' => now(),
                    ])->save();
                } else {
                    \App\Models\ProductStock::query()->create([
                        'warehouse_id' => $locked->to_warehouse_id,
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'on_hand' => (int) $item->quantity,
                        'reserved' => 0,
                        'incoming' => 0,
                        'last_counted_at' => now(),
                    ]);
                }
            }

            $locked->forceFill(['status' => 'received', 'received_at' => now()])->save();

            return $locked->fresh(['items']);
        }, 3);
    }

    public function cancelTransfer(\App\Models\StockTransfer $transfer): \App\Models\StockTransfer
    {
        return DB::transaction(function () use ($transfer): \App\Models\StockTransfer {
            $locked = \App\Models\StockTransfer::query()->lockForUpdate()->findOrFail($transfer->getKey());

            abort_if(! $locked->canCancel(), 422, 'Transfer yang sudah diterima tidak dapat dibatalkan.');

            $locked->forceFill(['status' => 'cancelled'])->save();

            return $locked->fresh();
        }, 3);
    }

    /**
     * Stock opname: cocokkan fisik vs sistem, tulis movement type=opname,
     * update products.current_stock + product_stocks.last_counted_at.
     */
    public function opname(Product $product, int $counted, ?int $warehouseId, string $note): StockMovement
    {
        abort_if((int) $product->shop_id !== $this->scope->shopId(), 403);

        return DB::transaction(function () use ($product, $counted, $warehouseId, $note): StockMovement {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $system = (int) $locked->current_stock;
            $diff = $counted - $system;

            $locked->forceFill(['current_stock' => max(0, $counted)])->save();

            if ($warehouseId !== null && $warehouseId > 0) {
                \App\Models\ProductStock::query()->updateOrCreate(
                    ['warehouse_id' => $warehouseId, 'product_id' => $locked->getKey(), 'product_variant_id' => null],
                    ['on_hand' => max(0, $counted), 'last_counted_at' => now()],
                );
            }

            return StockMovement::query()->create([
                'warehouse_id' => $warehouseId,
                'product_id' => $locked->getKey(),
                'product_variant_id' => null,
                'type' => 'opname',
                'quantity' => abs($diff),
                'balance_after' => max(0, $counted),
                'reference_type' => 'opname',
                'reference_id' => $locked->getKey(),
                'note' => 'Opname: sistem '.$system.', fisik '.$counted.'. '.VendorScope::clean($note, 180),
                'created_by' => $this->scope->userId(),
            ]);
        }, 3);
    }

    /** Laporan selisih opname memakai movement type=opname existing. */
    public function varianceReport(int $limit = 50)
    {
        return StockMovement::query()
            ->where('type', 'opname')
            ->whereHas('product', fn ($q) => $q->where('shop_id', $this->scope->shopId()))
            ->with(['product:id,name,sku', 'warehouse:id,name,code'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
