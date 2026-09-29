<?php

declare(strict_types=1);

namespace App\Services\Backoffice;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Multi-warehouse inventory.
 *
 * Every mutation runs inside a transaction and takes a row lock on the affected
 * `product_stocks` rows before reading them, so two concurrent adjustments can
 * never interleave into a negative balance. `products.current_stock` is the
 * denormalised total the storefront reads, so it is recomputed from the ledger
 * on every write rather than nudged by a delta.
 */
final class StockService
{
    public const MOVEMENT_TYPES = [
        'in' => 'Masuk',
        'out' => 'Keluar',
        'adjustment' => 'Penyesuaian',
        'transfer' => 'Transfer',
        'reservation' => 'Reservasi',
        'release' => 'Pelepasan',
        'return' => 'Retur',
        'opname' => 'Opname',
    ];

    /**
     * @return array<string, mixed>
     */
    public function overview(int $page = 1, int $perPage = 20, string $search = '', string $warehouseId = '', string $state = ''): array
    {
        $query = ProductStock::query()
            ->with(['product:id,name,sku,price,current_stock,low_stock_threshold,shop_id', 'warehouse:id,name,code']);

        if ($warehouseId !== '') {
            $query->where('warehouse_id', (int) $warehouseId);
        }

        if ($search !== '') {
            $query->whereHas('product', fn ($p) => $p->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%'));
        }

        $matching = (clone $query);

        $state === 'out' ? $matching->where('on_hand', '<=', 0) : null;
        $state === 'low' ? $matching->whereColumn('on_hand', '<=', 'safety_stock')->where('on_hand', '>', 0) : null;
        $state === 'healthy' ? $matching->whereColumn('on_hand', '>', 'safety_stock') : null;

        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $total = (int) (clone $query)->count();

        $stocks = $matching->orderBy('on_hand')->orderBy('id')
            ->forPage($page, $perPage)
            ->get();

        $rows = $stocks->map(fn (ProductStock $stock): array => $this->stockRow($stock))->all();

        $value = 0.0;
        $units = 0;
        foreach ($query->with('product:id,price')->cursor() as $stock) {
            $units += (int) $stock->on_hand;
            $value += (int) $stock->on_hand * (float) ($stock->product?->price ?? 0);
        }

        return [
            'rows' => $rows,
            'kpis' => [
                ['label' => 'Nilai Stok', 'value' => $value, 'money' => true, 'icon' => 'cash', 'color' => 'success', 'hint' => 'Total nilai on-hand di semua gudang'],
                ['label' => 'Unit di Gudang', 'value' => $units, 'icon' => 'package', 'color' => 'primary', 'hint' => 'Total unit on-hand'],
                ['label' => 'Lokasi Stok', 'value' => (int) ProductStock::query()->count(), 'icon' => 'layers', 'color' => 'info', 'hint' => 'Baris stok per produk dan gudang'],
                ['label' => 'Gudang Aktif', 'value' => (int) Warehouse::query()->where('is_active', true)->count(), 'icon' => 'building', 'color' => 'warning', 'hint' => 'Gudang yang dapat dipakai'],
            ],
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stockRow(ProductStock $stock): array
    {
        $product = $stock->product;
        $onHand = (int) $stock->on_hand;
        $reserved = (int) $stock->reserved;
        $safety = (int) $stock->safety_stock;
        $available = $onHand - $reserved;
        $price = (float) ($product?->price ?? 0);

        return [
            'id' => (int) $stock->id,
            'product_id' => (int) $stock->product_id,
            'product' => (string) ($product?->name ?? 'Produk dihapus'),
            'sku' => (string) ($product?->sku ?? ''),
            'warehouse' => (string) ($stock->warehouse?->name ?? '-'),
            'warehouse_code' => (string) ($stock->warehouse?->code ?? ''),
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'incoming' => (int) $stock->incoming,
            'available' => $available,
            'safety_stock' => $safety,
            'price' => $price,
            'value' => $onHand * $price,
            'state' => $onHand <= 0 ? 'out_of_stock' : ($available <= $safety ? 'low_stock' : 'healthy'),
            'last_counted_at' => (string) ($stock->last_counted_at?->format('Y-m-d H:i') ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function warehouses(): array
    {
        $rows = Warehouse::query()
            ->withCount('stocks')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        $stockTotals = DB::table('product_stocks')
            ->join('warehouses', 'warehouses.id', '=', 'product_stocks.warehouse_id')
            ->leftJoin('products', 'products.id', '=', 'product_stocks.product_id')
            ->whereNull('warehouses.deleted_at')
            ->groupBy('product_stocks.warehouse_id')
            ->selectRaw('product_stocks.warehouse_id, SUM(product_stocks.on_hand) as units, COALESCE(SUM(product_stocks.on_hand * products.price), 0) as value')
            ->get()
            ->keyBy('warehouse_id');

        return $rows->map(function (Warehouse $warehouse) use ($stockTotals): array {
            $totals = $stockTotals[$warehouse->id] ?? null;

            return [
                'id' => (int) $warehouse->id,
                'name' => (string) $warehouse->name,
                'code' => (string) $warehouse->code,
                'address' => (string) ($warehouse->address ?? ''),
                'city' => (string) ($warehouse->city ?? ''),
                'province' => (string) ($warehouse->province ?? ''),
                'postal_code' => (string) ($warehouse->postal_code ?? ''),
                'country' => (string) $warehouse->country,
                'phone' => (string) ($warehouse->phone ?? ''),
                'manager_name' => (string) ($warehouse->manager_name ?? ''),
                'is_default' => (bool) $warehouse->is_default,
                'is_active' => (bool) $warehouse->is_active,
                'skus' => (int) $warehouse->stocks_count,
                'units' => (int) ($totals->units ?? 0),
                'value' => (float) ($totals->value ?? 0),
                'created_at' => (string) ($warehouse->created_at?->format('Y-m-d H:i') ?? ''),
            ];
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function movements(int $page = 1, int $perPage = 25, string $search = '', string $type = ''): array
    {
        $query = StockMovement::query()
            ->with(['product:id,name,sku', 'warehouse:id,name,code', 'creator:id,name']);

        if ($type !== '' && array_key_exists($type, self::MOVEMENT_TYPES)) {
            $query->where('type', $type);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('note', 'like', '%'.$search.'%')
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%'));
            });
        }

        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('id')->forPage($page, $perPage)->get()
            ->map(fn (StockMovement $movement): array => [
                'id' => (int) $movement->id,
                'product' => (string) ($movement->product?->name ?? 'Produk dihapus'),
                'sku' => (string) ($movement->product?->sku ?? ''),
                'warehouse' => (string) ($movement->warehouse?->name ?? '-'),
                'type' => (string) $movement->type,
                'type_label' => self::MOVEMENT_TYPES[$movement->type] ?? $movement->type,
                'quantity' => (int) $movement->quantity,
                'balance_after' => $movement->balance_after === null ? null : (int) $movement->balance_after,
                'note' => (string) ($movement->note ?? ''),
                'creator' => (string) ($movement->creator?->name ?? 'Sistem'),
                'at' => (string) ($movement->created_at?->format('Y-m-d H:i') ?? ''),
            ])
            ->all();

        return [
            'rows' => $rows,
            'types' => self::MOVEMENT_TYPES,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transfers(int $page = 1, int $perPage = 20, string $status = ''): array
    {
        $query = StockTransfer::query()
            ->with(['fromWarehouse:id,name', 'toWarehouse:id,name', 'creator:id,name'])
            ->with(['items:id,stock_transfer_id,product_id,quantity,received_quantity']);

        if ($status !== '') {
            $query->where('status', $status);
        }

        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('id')->forPage($page, $perPage)->get()
            ->map(fn (StockTransfer $transfer): array => [
                'id' => (int) $transfer->id,
                'transfer_number' => (string) $transfer->transfer_number,
                'from' => (string) ($transfer->fromWarehouse?->name ?? '-'),
                'to' => (string) ($transfer->toWarehouse?->name ?? '-'),
                'status' => (string) $transfer->status,
                'items' => $transfer->items->map(fn (StockTransferItem $item): array => [
                    'id' => (int) $item->id,
                    'quantity' => (int) $item->quantity,
                    'received_quantity' => (int) $item->received_quantity,
                    'pending' => max(0, (int) $item->quantity - (int) $item->received_quantity),
                ])->all(),
                'note' => (string) ($transfer->note ?? ''),
                'creator' => (string) ($transfer->creator?->name ?? 'Sistem'),
                'created_at' => (string) ($transfer->created_at?->format('Y-m-d H:i') ?? ''),
                'received_at' => (string) ($transfer->received_at?->format('Y-m-d H:i') ?? ''),
            ])
            ->all();

        return [
            'rows' => $rows,
            'statuses' => ['draft', 'in_transit', 'received', 'cancelled'],
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transferDetail(StockTransfer $transfer): array
    {
        $transfer->loadMissing(['fromWarehouse:id,name,code', 'toWarehouse:id,name,code', 'creator:id,name', 'items.product:id,name,sku', 'items.productVariant:id,variant,sku']);

        return [
            'transfer' => [
                'id' => (int) $transfer->id,
                'transfer_number' => (string) $transfer->transfer_number,
                'from' => (string) ($transfer->fromWarehouse?->name ?? '-'),
                'to' => (string) ($transfer->toWarehouse?->name ?? '-'),
                'status' => (string) $transfer->status,
                'note' => (string) ($transfer->note ?? ''),
                'created_at' => (string) ($transfer->created_at?->format('Y-m-d H:i') ?? ''),
                'shipped_at' => (string) ($transfer->shipped_at?->format('Y-m-d H:i') ?? ''),
                'received_at' => (string) ($transfer->received_at?->format('Y-m-d H:i') ?? ''),
            ],
            'items' => $transfer->items->map(fn (StockTransferItem $item): array => [
                'id' => (int) $item->id,
                'product' => (string) ($item->product?->name ?? 'Produk dihapus'),
                'variant' => (string) ($item->productVariant?->variant ?? ''),
                'quantity' => (int) $item->quantity,
                'received_quantity' => (int) $item->received_quantity,
                'pending' => max(0, (int) $item->quantity - (int) $item->received_quantity),
            ])->all(),
        ];
    }

    public function createWarehouse(array $data, ?int $actorId): Warehouse
    {
        return DB::transaction(function () use ($data, $actorId): Warehouse {
            $code = strtoupper(trim((string) ($data['code'] ?? '')));

            if (Warehouse::withTrashed()->where('code', $code)->exists()) {
                throw ValidationException::withMessages(['code' => 'Kode gudang sudah digunakan.']);
            }

            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault) {
                Warehouse::query()->update(['is_default' => false]);
            }

            $warehouse = Warehouse::create([
                'name' => (string) $data['name'],
                'code' => $code,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'province' => $data['province'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'country' => $data['country'] ?? 'ID',
                'phone' => $data['phone'] ?? null,
                'manager_name' => $data['manager_name'] ?? null,
                'is_default' => $isDefault,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            app(AuditLogger::class)->log('warehouse.created', $warehouse, [], ['code' => $code], $actorId);

            return $warehouse;
        });
    }

    public function updateWarehouse(Warehouse $warehouse, array $data, ?int $actorId): Warehouse
    {
        return DB::transaction(function () use ($warehouse, $data, $actorId): Warehouse {
            $before = $warehouse->only(['name', 'code', 'is_default', 'is_active']);

            if (! empty($data['code'])) {
                $code = strtoupper(trim((string) $data['code']));
                if (Warehouse::withTrashed()->where('code', $code)->where('id', '!=', $warehouse->id)->exists()) {
                    throw ValidationException::withMessages(['code' => 'Kode gudang sudah digunakan.']);
                }
                $warehouse->code = $code;
            }

            if (! empty($data['is_default'])) {
                Warehouse::query()->where('id', '!=', $warehouse->id)->update(['is_default' => false]);
            }

            foreach (['name', 'address', 'city', 'province', 'postal_code', 'country', 'phone', 'manager_name'] as $field) {
                if (array_key_exists($field, $data)) {
                    $warehouse->{$field} = $data[$field];
                }
            }

            if (array_key_exists('is_default', $data)) {
                $warehouse->is_default = (bool) $data['is_default'];
            }

            if (array_key_exists('is_active', $data)) {
                $warehouse->is_active = (bool) $data['is_active'];
            }

            $warehouse->save();

            app(AuditLogger::class)->log('warehouse.updated', $warehouse, $before, $warehouse->only(['name', 'code', 'is_default', 'is_active']), $actorId);

            return $warehouse;
        });
    }

    /**
     * Manual correction of a stock level. The difference is written to
     * `stock_movements` as an `adjustment` row so the ledger explains itself.
     *
     * @return array{balance_after: int, delta: int}
     */
    public function adjust(int $productId, int $warehouseId, int $newOnHand, ?int $variantId, string $note, ?int $actorId): array
    {
        return DB::transaction(function () use ($productId, $warehouseId, $newOnHand, $variantId, $note, $actorId): array {
            $stock = $this->lockStock($productId, $warehouseId, $variantId);
            $delta = $newOnHand - (int) $stock->on_hand;

            if ($newOnHand < 0) {
                throw ValidationException::withMessages(['on_hand' => 'Stok tidak boleh negatif.']);
            }

            if ($delta !== 0) {
                $stock->forceFill([
                    'on_hand' => $newOnHand,
                    'last_counted_at' => now(),
                ])->save();

                StockMovement::create([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'type' => 'adjustment',
                    'quantity' => $delta,
                    'balance_after' => $newOnHand,
                    'reference_type' => 'admin_adjustment',
                    'note' => $note !== '' ? $note : 'Penyesuaian stok manual',
                    'created_by' => $actorId,
                ]);
            }

            $this->syncProductTotal($productId);

            app(AuditLogger::class)->log('stock.adjusted', $stock, ['on_hand' => $stock->getOriginal('on_hand')], ['on_hand' => $newOnHand, 'delta' => $delta], $actorId);

            return ['balance_after' => $newOnHand, 'delta' => $delta];
        });
    }

    /**
     * Bulk recount (opname): the counted quantity wins, the difference becomes
     * one `opname` movement per line.
     *
     * @param  list<array{product_id: int, warehouse_id: int, product_variant_id?: int|null, quantity: int}>  $lines
     * @return array{processed: int, changed: int, deltas: list<array<string, mixed>>}
     */
    public function opname(array $lines, ?int $actorId): array
    {
        return DB::transaction(function () use ($lines, $actorId): array {
            $processed = 0;
            $changed = 0;
            $deltas = [];

            foreach ($lines as $line) {
                $productId = (int) $line['product_id'];
                $warehouseId = (int) $line['warehouse_id'];
                $variantId = isset($line['product_variant_id']) && $line['product_variant_id'] !== null ? (int) $line['product_variant_id'] : null;
                $counted = max(0, (int) $line['quantity']);

                $stock = $this->lockStock($productId, $warehouseId, $variantId);
                $delta = $counted - (int) $stock->on_hand;

                $stock->forceFill(['on_hand' => $counted, 'last_counted_at' => now()])->save();

                if ($delta !== 0) {
                    StockMovement::create([
                        'warehouse_id' => $warehouseId,
                        'product_id' => $productId,
                        'product_variant_id' => $variantId,
                        'type' => 'opname',
                        'quantity' => $delta,
                        'balance_after' => $counted,
                        'reference_type' => 'stock_opname',
                        'note' => 'Selisih hasil hitung fisik',
                        'created_by' => $actorId,
                    ]);
                    $changed++;
                }

                $this->syncProductTotal($productId);
                $processed++;

                $deltas[] = [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'system' => $counted - $delta,
                    'counted' => $counted,
                    'delta' => $delta,
                ];
            }

            app(AuditLogger::class)->log('stock.opname', null, [], ['processed' => $processed, 'changed' => $changed], $actorId);

            return ['processed' => $processed, 'changed' => $changed, 'deltas' => $deltas];
        });
    }

    /**
     * Create and ship a transfer. The source rows are decremented immediately so
     * the goods cannot be promised twice while in transit.
     *
     * @param  list<array{product_id: int, quantity: int, product_variant_id?: int|null}>  $items
     */
    public function createTransfer(int $fromWarehouseId, int $toWarehouseId, array $items, ?string $note, ?int $actorId): StockTransfer
    {
        return DB::transaction(function () use ($fromWarehouseId, $toWarehouseId, $items, $note, $actorId): StockTransfer {
            if ($fromWarehouseId === $toWarehouseId) {
                throw ValidationException::withMessages(['to_warehouse_id' => 'Gudang tujuan harus berbeda dari gudang asal.']);
            }

            $transfer = StockTransfer::create([
                'transfer_number' => $this->nextTransferNumber(),
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'status' => 'in_transit',
                'note' => $note,
                'created_by' => $actorId,
                'shipped_at' => now(),
            ]);

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $quantity = (int) $item['quantity'];
                $variantId = isset($item['product_variant_id']) && $item['product_variant_id'] !== null ? (int) $item['product_variant_id'] : null;

                if ($quantity <= 0) {
                    continue;
                }

                $stock = $this->lockStock($productId, $fromWarehouseId, $variantId);
                $available = (int) $stock->on_hand - (int) $stock->reserved;

                if ($quantity > $available) {
                    throw ValidationException::withMessages([
                        'items' => 'Stok produk #'.$productId.' tidak cukup untuk dipindahkan (tersedia '.$available.').',
                    ]);
                }

                $balance = (int) $stock->on_hand - $quantity;
                $stock->forceFill(['on_hand' => $balance])->save();

                StockMovement::create([
                    'warehouse_id' => $fromWarehouseId,
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'type' => 'transfer',
                    'quantity' => -$quantity,
                    'balance_after' => $balance,
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $transfer->id,
                    'note' => 'Kirim ke transfer '.$transfer->transfer_number,
                    'created_by' => $actorId,
                ]);

                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                    'received_quantity' => 0,
                ]);

                $this->syncProductTotal($productId);
            }

            if ($transfer->items()->count() === 0) {
                throw ValidationException::withMessages(['items' => 'Transfer harus memiliki minimal satu produk.']);
            }

            app(AuditLogger::class)->log('stock.transfer.created', $transfer, [], [
                'from' => $fromWarehouseId,
                'to' => $toWarehouseId,
                'items' => $transfer->items()->count(),
            ], $actorId);

            return $transfer;
        });
    }

    /**
     * Receive a transfer, optionally partially. Receiving is idempotent per line:
     * the delta between what was already received and what is being received now
     * is the quantity that actually moves.
     *
     * @param  array<int, int>  $received  item id => quantity received now
     */
    public function receiveTransfer(StockTransfer $transfer, array $received, ?int $actorId): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $received, $actorId): StockTransfer {
            $locked = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($locked->status === 'received') {
                throw ValidationException::withMessages(['status' => 'Transfer ini sudah diterima.']);
            }

            if ($locked->status === 'cancelled') {
                throw ValidationException::withMessages(['status' => 'Transfer ini sudah dibatalkan.']);
            }

            $items = $locked->items()->lockForUpdate()->get();
            $touchedProducts = [];

            foreach ($items as $item) {
                $wanted = (int) ($received[$item->id] ?? $item->quantity);
                $remaining = (int) $item->quantity - (int) $item->received_quantity;
                $quantity = max(0, min($wanted, $remaining));

                if ($quantity === 0) {
                    continue;
                }

                $stock = $this->lockStock((int) $item->product_id, (int) $locked->to_warehouse_id, $item->product_variant_id);
                $balance = (int) $stock->on_hand + $quantity;

                $stock->forceFill(['on_hand' => $balance])->save();

                StockMovement::create([
                    'warehouse_id' => (int) $locked->to_warehouse_id,
                    'product_id' => (int) $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'type' => 'transfer',
                    'quantity' => $quantity,
                    'balance_after' => $balance,
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $locked->id,
                    'note' => 'Terima dari transfer '.$locked->transfer_number,
                    'created_by' => $actorId,
                ]);

                $item->forceFill(['received_quantity' => (int) $item->received_quantity + $quantity])->save();
                $touchedProducts[] = (int) $item->product_id;
            }

            foreach (array_unique($touchedProducts) as $productId) {
                $this->syncProductTotal($productId);
            }

            $stillOutstanding = $locked->items()->get()->contains(fn (StockTransferItem $item): bool => (int) $item->received_quantity < (int) $item->quantity);

            $locked->forceFill($stillOutstanding ? [] : ['status' => 'received', 'received_at' => now()])->save();

            app(AuditLogger::class)->log('stock.transfer.received', $locked, ['status' => $transfer->status], ['status' => $locked->status], $actorId);

            return $locked->refresh();
        });
    }

    /**
     * Saran alokasi gudang otomatis (aditif, read-only): tanpa mengunci stok,
     * kembalikan gudang terbaik + rincian ketersediaan per produk.
     *
     * @param  array<int, int>  $needs  product_id => qty
     * @return array{warehouse: ?Warehouse, available: array<int,int>, full: bool}
     */
    public function suggestWarehouse(array $needs, ?string $destinationCity = null): array
    {
        $needs = array_filter(array_map('intval', $needs), fn (int $qty): bool => $qty > 0);

        if ($needs === []) {
            return ['warehouse' => null, 'available' => [], 'full' => false];
        }

        $warehouse = app(\App\Services\Shipping\ShippingService::class)
            ->allocateWarehouseForItems($needs, $destinationCity);

        $available = [];

        if ($warehouse !== null) {
            $rows = ProductStock::query()
                ->where('warehouse_id', $warehouse->id)
                ->whereIn('product_id', array_keys($needs))
                ->get(['product_id', 'on_hand', 'reserved']);

            foreach ($needs as $productId => $qty) {
                $row = $rows->firstWhere('product_id', $productId);
                $available[$productId] = $row ? max(0, (int) $row->on_hand - (int) $row->reserved) : 0;
            }
        }

        $full = $warehouse !== null && collect($needs)->every(fn (int $qty, int $pid): bool => ($available[$pid] ?? 0) >= $qty);

        return ['warehouse' => $warehouse, 'available' => $available, 'full' => $full];
    }

    /**
     * Row lock the stock line, creating it when the pair has never been seen.
     */
    private function lockStock(int $productId, int $warehouseId, ?int $variantId): ProductStock
    {
        $query = ProductStock::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where(fn ($q) => $variantId === null ? $q->whereNull('product_variant_id') : $q->where('product_variant_id', $variantId))
            ->lockForUpdate();

        $stock = $query->first();

        if ($stock !== null) {
            return $stock;
        }

        try {
            return ProductStock::create([
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'on_hand' => 0,
                'reserved' => 0,
                'incoming' => 0,
                'safety_stock' => 0,
            ]);
        } catch (\Throwable) {
            return ProductStock::query()
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where(fn ($q) => $variantId === null ? $q->whereNull('product_variant_id') : $q->where('product_variant_id', $variantId))
                ->lockForUpdate()
                ->firstOrFail();
        }
    }

    /**
     * Recompute the denormalised storefront total from the ledger.
     */
    private function syncProductTotal(int $productId): void
    {
        $total = (int) ProductStock::query()
            ->where('product_id', $productId)
            ->get()
            ->sum(fn (ProductStock $stock): int => (int) $stock->on_hand - (int) $stock->reserved);

        Product::query()->whereKey($productId)->update(['current_stock' => $total]);
    }

    private function nextTransferNumber(): string
    {
        do {
            $number = 'TRF-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (StockTransfer::query()->where('transfer_number', $number)->exists());

        return $number;
    }
}
