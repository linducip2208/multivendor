<?php

declare(strict_types=1);

namespace App\Services\Vendor;

use App\Domain\Order\OrderStateMachine;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderShipment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\AuditLogger;
use App\Services\OrderWorkflowService;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Shipping an order out of the vendor back-office.
 *
 * A shipment is a custody record: it is written once, before the order status
 * moves, so a crash between the two cannot leave an order marked shipped with
 * no tracking row. The state transition itself is delegated to
 * OrderWorkflowService so the state machine stays authoritative.
 */
final class VendorFulfillmentService
{
    public function __construct(private readonly VendorScope $scope) {}

    /** @return list<string> */
    public static function shippableStatuses(): array
    {
        return [
            OrderStatus::Paid->stored(),
            OrderStatus::Confirmed->stored(),
            OrderStatus::Processing->stored(),
            OrderStatus::Packed->stored(),
        ];
    }

    public function ship(Order $order, array $payload): OrderShipment
    {
        return DB::transaction(function () use ($order, $payload): OrderShipment {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            abort_if((int) $locked->shop_id !== $this->scope->shopId(), 403);

            OrderStateMachine::assertCanTransition(
                (string) $locked->order_status,
                OrderStatus::Shipped,
                (string) $locked->payment_status,
            );

            $items = OrderItem::query()
                ->where('order_id', $locked->getKey())
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Pesanan tidak memiliki item.']);
            }

            $deductStock = ! $locked->stock_released_at && ! $this->alreadyDeducted($locked);

            if ($deductStock) {
                $this->deductStock($items, $payload);
            }

            $shipment = OrderShipment::query()->create([
                'order_id' => $locked->getKey(),
                'provider_id' => $payload['provider_id'] ?? null,
                'courier' => VendorScope::cleanNullable($payload['courier'] ?? null, 40),
                'service' => VendorScope::cleanNullable($payload['service'] ?? null, 80),
                'tracking_number' => VendorScope::cleanNullable($payload['tracking_number'] ?? null, 80),
                'label_url' => VendorScope::cleanNullable($payload['label_url'] ?? null, 255),
                'weight' => $payload['weight'] ?? null,
                'cost' => Money::of($payload['cost'] ?? 0)->maxZero()->toDecimal(),
                'status' => 'shipped',
                'tracking_history' => [[
                    'status' => 'shipped',
                    'occurred_at' => now()->toIso8601String(),
                    'description' => VendorScope::clean($payload['note'] ?? 'Barang dikirim oleh penjual.', 255),
                ]],
                'shipped_at' => now(),
            ]);

            $locked->forceFill([
                'fulfillment_status' => 'fulfilled',
                'packed_at' => $locked->packed_at ?? now(),
            ])->save();

            OrderItem::query()->where('order_id', $locked->getKey())
                ->update(['fulfillment_status' => 'fulfilled']);

            app(OrderWorkflowService::class)->ship(
                $locked,
                $this->scope->userId(),
                $shipment->tracking_number,
                VendorScope::cleanNullable($payload['note'] ?? null, 255),
            );

            app(AuditLogger::class)->log('vendor.order.shipped', $locked, [
                'order_status' => $locked->order_status,
            ], [
                'shipment_id' => $shipment->getKey(),
                'courier' => $shipment->courier,
                'tracking_number' => $shipment->tracking_number,
            ], $this->scope->userId());

            return $shipment->refresh();
        }, 3);
    }

    /** @return array{total: int, value: \App\Support\Money, orders: \Illuminate\Support\Collection<int, Order>} */
    public function queue(int $limit = 25): array
    {
        $shopId = $this->scope->shopId();

        $query = Order::query()
            ->where('shop_id', $shopId)
            ->whereIn('order_status', self::shippableStatuses())
            ->where('fulfillment_status', '!=', 'fulfilled');

        $total = (int) (clone $query)->count();
        $orders = $query->with(['customer:id,name', 'items'])
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        return [
            'total' => $total,
            'value' => Money::sum($orders->pluck('total')),
            'orders' => $orders,
        ];
    }

    /**
     * Bulk fulfillment: kirim banyak pesanan sekaligus. Tiap pesanan diproses
     * dalam transaksinya sendiri (atomicity per order) via ship().
     *
     * @param  list<int>  $orderIds
     * @return array{ok: list<int>, fail: array<int,string>, shipments: list<int>}
     */
    public function bulkShip(array $orderIds, array $payload): array
    {
        $ok = [];
        $fail = [];
        $shipments = [];

        foreach (array_values(array_unique(array_map('intval', $orderIds))) as $id) {
            if ($id <= 0) {
                continue;
            }

            try {
                $order = Order::query()->where('shop_id', $this->scope->shopId())->whereKey($id)->firstOrFail();
                $shipments[] = (int) $this->ship($order, $payload)->getKey();
                $ok[] = $id;
            } catch (\Throwable $e) {
                $fail[$id] = $e instanceof ValidationException
                    ? (string) collect($e->errors())->flatten()->first()
                    : 'Gagal mengirim pesanan.';
            }
        }

        return ['ok' => $ok, 'fail' => $fail, 'shipments' => $shipments];
    }

    /**
     * Ubah status massal (confirmed/processing/packed) memakai state machine.
     *
     * @param  list<int>  $orderIds
     * @return array{ok: list<int>, fail: array<int,string>}
     */
    public function bulkStatus(array $orderIds, string $status, ?string $note = null): array
    {
        $to = match ($status) {
            'confirmed' => OrderStatus::Confirmed,
            'processing' => OrderStatus::Processing,
            'packed' => OrderStatus::Packed,
            default => null,
        };

        if ($to === null) {
            throw ValidationException::withMessages(['status' => 'Status massal tidak valid.']);
        }

        $scoped = Order::query()->where('shop_id', $this->scope->shopId())
            ->whereIn('id', array_values(array_unique(array_map('intval', $orderIds))))
            ->pluck('id')->all();

        return app(OrderWorkflowService::class)->bulkTransition($scoped, $to, $this->scope->userId(), $note);
    }

    // ── Logistik lanjutan (aditif): alokasi, pickup, manifest, jemput retur ──

    /**
     * Alokasi gudang otomatis untuk satu pesanan toko ini (read-only):
     * gudang bertok + terdekat kota tujuan, fallback gudang utama.
     *
     * @return array{warehouse: ?Warehouse, items: array<int,int>, full: bool}
     */
    public function allocateForOrder(Order $order, ?string $destinationCity = null): array
    {
        abort_if((int) $order->shop_id !== $this->scope->shopId(), 403);

        $items = OrderItem::query()->where('order_id', $order->getKey())->get(['product_id', 'quantity']);
        $needs = [];

        foreach ($items as $item) {
            $needs[(int) $item->product_id] = ($needs[(int) $item->product_id] ?? 0) + max(0, (int) $item->quantity);
        }

        $city = $destinationCity ?? (is_array($order->shipping_address) ? (string) ($order->shipping_address['city'] ?? '') : '');

        $warehouse = app(\App\Services\Shipping\ShippingService::class)
            ->allocateWarehouseForItems($needs, $city !== '' ? $city : null);

        $full = $warehouse !== null && app(\App\Services\Backoffice\StockService::class)
            ->suggestWarehouse($needs, $city !== '' ? $city : null)['full'];

        return ['warehouse' => $warehouse, 'items' => $needs, 'full' => $full];
    }

    /** Buat kiriman ambil di toko untuk pesanan milik toko ini. */
    public function createPickup(Order $order, int $warehouseId): OrderShipment
    {
        abort_if((int) $order->shop_id !== $this->scope->shopId(), 403);

        return app(\App\Services\Backoffice\FulfillmentService::class)
            ->createPickupShipment($order, $warehouseId, $this->scope->userId());
    }

    /** Tandai kiriman pickup milik toko ini siap diambil. */
    public function readyPickup(OrderShipment $shipment): OrderShipment
    {
        $this->assertOwnShipment($shipment);

        return app(\App\Services\Backoffice\FulfillmentService::class)
            ->readyForPickup($shipment, $this->scope->userId());
    }

    /** Verifikasi kode ambil milik toko ini (satu kali pakai). */
    public function verifyPickup(Order $order, string $code): OrderShipment
    {
        abort_if((int) $order->shop_id !== $this->scope->shopId(), 403);

        return app(\App\Services\Backoffice\FulfillmentService::class)
            ->verifyPickup($order, $code, $this->scope->userId());
    }

    /** Kiriman pickup milik toko ini (siap / menunggu verifikasi). */
    public function pickupQueue(int $limit = 25)
    {
        return OrderShipment::query()
            ->where('is_pickup', true)
            ->whereHas('order', fn ($q) => $q->where('shop_id', $this->scope->shopId()))
            ->with(['order:id,order_number,shop_id'])
            ->orderByDesc('id')
            ->limit(max(5, min(100, $limit)))
            ->get();
    }

    /**
     * Batch manifest AWB khusus kiriman toko ini: beri nomor manifest pada
     * kiriman pilihan yang belum bermanifest. Idempoten per baris.
     *
     * @param  list<int>  $shipmentIds
     * @return array{manifest_no: string, manifest_date: string, total: int}
     */
    public function manifestBatchForShop(array $shipmentIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $shipmentIds)));
        $ids = array_filter($ids, fn (int $id): bool => $id > 0);

        $owned = OrderShipment::query()
            ->whereIn('id', $ids)
            ->whereHas('order', fn ($q) => $q->where('shop_id', $this->scope->shopId()))
            ->pluck('id')
            ->all();

        return app(\App\Services\Backoffice\FulfillmentService::class)
            ->manifestBatch($owned, $this->scope->userId());
    }

    /** Rekap manifest per kurir per hari khusus toko ini. */
    public function manifestRecapForShop(?string $courier = null, ?string $date = null): array
    {
        $query = OrderShipment::query()
            ->whereHas('order', fn ($q) => $q->where('shop_id', $this->scope->shopId()))
            ->selectRaw("COALESCE(NULLIF(courier,''),'MANUAL') as courier, COALESCE(manifest_date, DATE(order_shipments.created_at)) as manifest_day, COALESCE(NULLIF(manifest_no,''),'-') as manifest_no, COUNT(*) as total")
            ->groupBy('courier', 'manifest_day', 'manifest_no')
            ->orderBy('manifest_day', 'desc')
            ->orderBy('courier');

        if ($courier !== null && trim($courier) !== '') {
            $query->where('courier', 'like', '%'.trim($courier).'%');
        }

        if ($date !== null && trim($date) !== '') {
            $query->whereDate('manifest_date', trim($date));
        }

        try {
            return $query->limit(100)->get()->map(fn ($row): array => [
                'courier' => (string) $row->courier,
                'date' => (string) $row->manifest_day,
                'manifest_no' => (string) $row->manifest_no,
                'total' => (int) $row->total,
            ])->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** Data label massal dari order_shipments existing. */
    public function labelsFor(array $orderIds): array
    {
        return OrderShipment::query()
            ->whereIn('order_id', array_values(array_unique(array_map('intval', $orderIds))))
            ->whereHas('order', fn ($q) => $q->where('shop_id', $this->scope->shopId()))
            ->with('order:id,order_number,shop_id')
            ->orderBy('created_at')
            ->get()
            ->map(fn (OrderShipment $s): array => array_merge($s->labelData(), [
                'nomor_pesanan' => (string) ($s->order?->order_number ?? '-'),
                // Aditif logistik: nomor manifest + kode ambil (kolom baru, aman bila null).
                'manifest_no' => (string) ($s->manifest_no ?? ''),
                'manifest_date' => (string) ($s->manifest_date?->format('Y-m-d') ?? ''),
                'is_pickup' => (bool) ($s->is_pickup ?? false),
                'pickup_code' => (string) ($s->pickup_code ?? ''),
            ]))
            ->all();
    }

    /** Baris ekspor CSV fulfillment (header + rows). */
    public function exportRows(array $orderIds): array
    {
        $header = ['Nomor Pesanan', 'Kurir', 'Layanan', 'No. Resi', 'Status', 'Biaya'];

        $rows = OrderShipment::query()
            ->whereIn('order_id', array_values(array_unique(array_map('intval', $orderIds))))
            ->whereHas('order', fn ($q) => $q->where('shop_id', $this->scope->shopId()))
            ->with('order:id,order_number')
            ->orderBy('created_at')
            ->get()
            ->map(fn (OrderShipment $s): array => $s->toExportRow())
            ->all();

        return [$header, ...$rows];
    }

    /**
     * Customer back-in-stock signals for this shop's catalogue. A restock
     * request belongs to the product, so ownership is proven through the
     * product's shop rather than a shop column that does not exist.
     */
    public function restockRequests(int $limit = 50)
    {
        return DB::table('restock_requests')
            ->join('products', 'products.id', '=', 'restock_requests.product_id')
            ->where('products.shop_id', $this->scope->shopId())
            ->leftJoin('users', 'users.id', '=', 'restock_requests.customer_id')
            ->select([
                'restock_requests.id',
                'restock_requests.product_id',
                'restock_requests.customer_id',
                'restock_requests.status',
                'restock_requests.created_at',
                'products.name as product_name',
                'products.sku as product_sku',
                'products.current_stock',
                'users.name as customer_name',
                'users.email as customer_email',
            ])
            ->orderByDesc('restock_requests.created_at')
            ->limit($limit)
            ->get();
    }

    public function notifyRestock(int $restockRequestId): int
    {
        return DB::transaction(function () use ($restockRequestId): int {
            $row = DB::table('restock_requests')
                ->join('products', 'products.id', '=', 'restock_requests.product_id')
                ->where('restock_requests.id', $restockRequestId)
                ->where('products.shop_id', $this->scope->shopId())
                ->lockForUpdate()
                ->select(['restock_requests.id', 'restock_requests.status', 'products.current_stock'])
                ->first();

            abort_if($row === null, 404);

            if ($row->status === 'notified') {
                return 0;
            }

            DB::table('restock_requests')
                ->where('id', $row->id)
                ->update(['status' => 'notified', 'updated_at' => now()]);

            return 1;
        }, 3);
    }

    /** Jadwalkan penjemputan retur milik toko ini (retur harus disetujui). */
    public function scheduleReturnPickup(\App\Models\OrderReturn $return, array $data): \App\Models\OrderReturn
    {
        $this->assertOwnReturn($return);

        return app(\App\Services\Backoffice\FulfillmentService::class)
            ->scheduleReturnPickup($return, $data, $this->scope->userId());
    }

    /** Data label retur milik toko ini untuk dicetak. */
    public function returnLabelFor(\App\Models\OrderReturn $return): array
    {
        $this->assertOwnReturn($return);

        return app(\App\Services\Backoffice\FulfillmentService::class)->returnLabelData($return);
    }

    private function assertOwnShipment(OrderShipment $shipment): void
    {
        $owned = OrderShipment::query()
            ->whereKey($shipment->getKey())
            ->whereHas('order', fn ($q) => $q->where('shop_id', $this->scope->shopId()))
            ->exists();

        abort_if(! $owned, 403);
    }

    private function assertOwnReturn(\App\Models\OrderReturn $return): void
    {
        $owned = \App\Models\OrderReturn::query()
            ->whereKey($return->getKey())
            ->whereHas('order', fn ($q) => $q->where('shop_id', $this->scope->shopId()))
            ->exists();

        abort_if(! $owned, 403);
    }

    private function alreadyDeducted(Order $order): bool
    {
        return DB::table('stock_movements')
            ->where('reference_type', 'order')
            ->where('reference_id', $order->getKey())
            ->where('type', 'out')
            ->exists();
    }

    /** @param \Illuminate\Support\Collection<int, OrderItem> $items */
    private function deductStock(\Illuminate\Support\Collection $items, array $payload): void
    {
        $warehouseId = $payload['warehouse_id'] ?? $this->defaultWarehouseId();
        $shopId = $this->scope->shopId();

        foreach ($items as $item) {
            $quantity = max(0, (int) $item->quantity);

            if ($quantity === 0) {
                continue;
            }

            if ($item->product_variant_id !== null) {
                $variant = ProductVariant::query()
                    ->where('product_id', $item->product_id)
                    ->whereKey($item->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                if ($variant !== null) {
                    $this->assertStock($variant->stock, $quantity, $item);
                    $variant->increment('stock', -$quantity);
                }

                continue;
            }

            $product = Product::query()
                ->where('shop_id', $shopId)
                ->whereKey($item->product_id)
                ->lockForUpdate()
                ->first();

            if ($product === null) {
                continue;
            }

            $this->assertStock((int) $product->current_stock, $quantity, $item);
            $product->increment('current_stock', -$quantity);

            DB::table('stock_movements')->insert([
                'warehouse_id' => $warehouseId,
                'product_id' => $product->getKey(),
                'product_variant_id' => $item->product_variant_id,
                'type' => 'out',
                'quantity' => $quantity,
                'balance_after' => (int) $product->current_stock - $quantity,
                'reference_type' => 'order',
                'reference_id' => (int) $item->order_id,
                'note' => 'Pengiriman pesanan',
                'created_by' => $this->scope->userId(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function assertStock(int $available, int $quantity, OrderItem $item): void
    {
        if ($available >= $quantity) {
            return;
        }

        throw ValidationException::withMessages([
            'items' => 'Stok tidak cukup untuk salah satu produk pada pesanan. Perbarui stok atau hapus item sebelum mengirim.',
        ]);
    }

    private function defaultWarehouseId(): ?int
    {
        try {
            return Warehouse::query()->orderBy('id')->value('id');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function reference(): string
    {
        return Str::upper(Str::random(10));
    }
}
