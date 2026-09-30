<?php

declare(strict_types=1);

namespace App\Services\Backoffice;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\OrderShipment;
use App\Models\Provider;
use App\Models\Warehouse;
use App\Services\AuditLogger;
use App\Services\Shipping\ShippingService;
use App\Support\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Shipment tracking and the return (RMA) decision desk.
 */
final class FulfillmentService
{
    public const PAGE_SIZE = 20;

    public const RETURN_STATUSES = [
        'requested' => 'Diminta',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'received' => 'Diterima',
        'refunded' => 'Dikembalikan',
    ];

    public function __construct(private readonly ShippingService $shipping) {}

    /**
     * @return array<string, mixed>
     */
    public function shipments(int $page = 1, string $status = '', string $search = ''): array
    {
        $query = OrderShipment::query()
            ->with(['order:id,order_number,customer_id,total,order_status', 'provider:id,name,type,api_format'])
            ->withCount('order');

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('tracking_number', 'like', '%'.$search.'%')
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', '%'.$search.'%'));
            });
        }

        $page = max(1, $page);
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('id')->forPage($page, self::PAGE_SIZE)->get()
            ->map(fn (OrderShipment $shipment): array => [
                'id' => (int) $shipment->id,
                'order_number' => (string) ($shipment->order?->order_number ?? '-'),
                'order_id' => (int) $shipment->order_id,
                'courier' => (string) ($shipment->courier ?? ($shipment->provider?->name ?? '-')),
                'service' => (string) ($shipment->service ?? '-'),
                'tracking_number' => (string) ($shipment->tracking_number ?? '-'),
                'status' => (string) $shipment->status,
                'cost' => (float) $shipment->cost,
                'cost_formatted' => Currency::format((float) $shipment->cost),
                'weight' => $shipment->weight,
                'shipped_at' => (string) ($shipment->shipped_at?->format('Y-m-d H:i') ?? ''),
                'delivered_at' => (string) ($shipment->delivered_at?->format('Y-m-d H:i') ?? ''),
                'history_steps' => is_array($shipment->tracking_history) ? count($shipment->tracking_history) : 0,
            ])
            ->all();

        return [
            'rows' => $rows,
            'counts' => $this->shipmentCounts(),
            'pagination' => [
                'total' => $total,
                'per_page' => self::PAGE_SIZE,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / self::PAGE_SIZE)),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function shipmentCounts(): array
    {
        $counts = ['all' => 0, 'pending' => 0, 'shipped' => 0, 'in_transit' => 0, 'delivered' => 0, 'returned' => 0, 'failed' => 0];

        try {
            $counts['all'] = (int) OrderShipment::query()->count();
            foreach (OrderShipment::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->get() as $row) {
                $key = (string) $row->status;
                if (array_key_exists($key, $counts)) {
                    $counts[$key] = (int) $row->aggregate;
                }
            }
        } catch (\Throwable) {
            foreach ($counts as $key => $_) {
                $counts[$key] = 0;
            }
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    public function shipmentDetail(OrderShipment $shipment): array
    {
        $shipment->loadMissing(['order.customer:id,name,email,phone', 'order.items:id,order_id,product_id,quantity,sub_total', 'order.items.product:id,name,sku', 'provider:id,name,api_format,base_url']);

        $history = is_array($shipment->tracking_history) ? $shipment->tracking_history : [];

        return [
            'shipment' => [
                'id' => (int) $shipment->id,
                'order_id' => (int) $shipment->order_id,
                'order_number' => (string) ($shipment->order?->order_number ?? '-'),
                'courier' => (string) ($shipment->courier ?? ($shipment->provider?->name ?? '-')),
                'service' => (string) ($shipment->service ?? '-'),
                'tracking_number' => (string) ($shipment->tracking_number ?? ''),
                'label_url' => (string) ($shipment->label_url ?? ''),
                'status' => (string) $shipment->status,
                'cost' => (float) $shipment->cost,
                'cost_formatted' => Currency::format((float) $shipment->cost),
                'weight' => $shipment->weight,
                'shipped_at' => (string) ($shipment->shipped_at?->format('Y-m-d H:i') ?? ''),
                'delivered_at' => (string) ($shipment->delivered_at?->format('Y-m-d H:i') ?? ''),
                'provider_id' => $shipment->provider_id,
                'has_provider' => $shipment->provider !== null,
            ],
            'order' => [
                'customer' => (string) ($shipment->order?->customer?->name ?? '-'),
                'email' => (string) ($shipment->order?->customer?->email ?? ''),
                'phone' => (string) ($shipment->order?->customer?->phone ?? ''),
                'order_status' => (string) ($shipment->order?->order_status ?? ''),
                'payment_status' => (string) ($shipment->order?->payment_status ?? ''),
                'total' => (float) ($shipment->order?->total ?? 0),
                'total_formatted' => Currency::format((float) ($shipment->order?->total ?? 0)),
                'address' => $this->addressLines($shipment->order?->shipping_address),
            ],
            'items' => $shipment->order?->items->map(fn ($item): array => [
                'name' => (string) ($item->product?->name ?? 'Produk dihapus'),
                'sku' => (string) ($item->product?->sku ?? ''),
                'quantity' => (int) $item->quantity,
                'sub_total' => (float) $item->sub_total,
                'sub_total_formatted' => Currency::format((float) $item->sub_total),
            ])->all() ?? [],
            'history' => array_map(fn (mixed $step): array => [
                'description' => is_array($step) ? (string) ($step['description'] ?? '') : (string) $step,
                'at' => is_array($step) ? (string) ($step['at'] ?? '') : '',
            ], $history),
        ];
    }

    /**
     * @return list<string>
     */
    private function addressLines(mixed $address): array
    {
        if (! is_array($address)) {
            return [];
        }

        $lines = [
            (string) ($address['receiver_name'] ?? ''),
            trim(implode(', ', array_filter([
                (string) ($address['address'] ?? ''),
                (string) ($address['city'] ?? ''),
                (string) ($address['province'] ?? ''),
                (string) ($address['postal_code'] ?? ''),
            ]))),
            (string) ($address['receiver_phone'] ?? ''),
        ];

        return array_values(array_filter($lines));
    }

    /**
     * @return array<string, mixed>
     */
    public function couriers(): array
    {
        $out = [];

        foreach ($this->shipping->getActiveProviders() as $provider) {
            $out[] = $this->courierRow($provider);
        }

        if ($out === []) {
            $out[] = [
                'id' => null,
                'name' => 'Belum ada provider',
                'api_format' => '-',
                'base_url' => '',
                'host' => '-',
                'is_active' => false,
                'is_default' => false,
                'configured' => false,
                'recent_shipments' => 0,
            ];
        }

        return [
            'rows' => $out,
            'configured_couriers' => $this->shipping->activeCouriers(),
            'default_courier' => $this->shipping->defaultCourier(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function courierDetail(Provider $provider): array
    {
        $row = $this->courierRow($provider);

        $row['deliveries'] = (int) OrderShipment::query()
            ->where(function (Builder $q) use ($provider): void {
                $q->where('provider_id', $provider->id)->orWhere('courier', 'like', '%'.$provider->name.'%');
            })
            ->count();

        $row['recent'] = OrderShipment::query()
            ->with('order:id,order_number')
            ->where('provider_id', $provider->id)
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (OrderShipment $shipment): array => [
                'order_id' => (int) $shipment->order_id,
                'order_number' => (string) ($shipment->order?->order_number ?? '-'),
                'tracking_number' => (string) ($shipment->tracking_number ?? '-'),
                'status' => (string) $shipment->status,
                'shipped_at' => (string) ($shipment->shipped_at?->format('Y-m-d H:i') ?? ''),
            ])
            ->all();

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function courierRow(Provider $provider): array
    {
        return [
            'id' => (int) $provider->id,
            'name' => (string) $provider->name,
            'api_format' => (string) $provider->api_format,
            'base_url' => (string) $provider->base_url,
            'host' => (string) (parse_url((string) $provider->base_url, PHP_URL_HOST) ?: '-'),
            'is_active' => (bool) $provider->is_active,
            'is_default' => (bool) $provider->is_default,
            'configured' => $provider->getApiKeyAttribute() !== null,
            'recent_shipments' => (int) OrderShipment::query()->where('provider_id', $provider->id)->count(),
        ];
    }

    /**
     * Live courier lookup. Never returns the upstream body verbatim.
     *
     * @return array{success: bool, steps: list<array{description: string, at: string}>, message: string}
     */
    public function track(OrderShipment $shipment): array
    {
        $fallback = array_map(fn (mixed $step): array => [
            'description' => is_array($step) ? (string) ($step['description'] ?? '') : (string) $step,
            'at' => is_array($step) ? (string) ($step['at'] ?? '') : '',
        ], is_array($shipment->tracking_history) ? $shipment->tracking_history : []);

        if ($shipment->provider === null || $shipment->tracking_number === null || $shipment->tracking_number === '') {
            return [
                'success' => false,
                'steps' => $fallback,
                'message' => 'Pengiriman ini belum terhubung ke provider kurir atau belum memiliki nomor resi.',
            ];
        }

        $result = $this->shipping->getTracking($shipment->provider, (string) $shipment->tracking_number, $shipment->courier);

        if (($result['success'] ?? false) !== true) {
            return [
                'success' => false,
                'steps' => $fallback,
                'message' => (string) ($result['message'] ?? 'Status kiriman sedang tidak dapat diambil.'),
            ];
        }

        $steps = $this->normaliseTracking(is_array($result['data'] ?? null) ? $result['data'] : []);

        if ($steps !== []) {
            $shipment->forceFill([
                'tracking_history' => $steps,
                'status' => $this->statusFromSteps($steps, (string) $shipment->status),
            ])->save();
        }

        return [
            'success' => true,
            'steps' => $steps !== [] ? $steps : $fallback,
            'message' => $steps === [] && $fallback === [] ? 'Provider belum mengembalikan riwayat pergerakan.' : 'Riwayat diperbarui dari provider kurir.',
        ];
    }

    /**
     * @return list<array{description: string, at: string}>
     */
    private function normaliseTracking(array $data): array
    {
        $candidates = [];

        foreach (['tracking_history', 'history', 'manifest', 'deliveries', 'data'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $candidates = $data[$key];
                break;
            }
        }

        $steps = [];

        foreach ($candidates as $step) {
            if (! is_array($step)) {
                continue;
            }

            $description = $step['description'] ?? $step['note'] ?? $step['status'] ?? '';
            $at = $step['at'] ?? $step['date'] ?? $step['time'] ?? '';

            if ($description === '' && $at === '') {
                continue;
            }

            $steps[] = ['description' => (string) $description, 'at' => (string) $at];
        }

        usort($steps, fn (array $a, array $b): int => strcmp($a['at'], $b['at']));

        return $steps;
    }

    /**
     * @param  list<array{description: string, at: string}>  $steps
     */
    private function statusFromSteps(array $steps, string $current): string
    {
        $last = end($steps);
        $text = mb_strtolower((string) ($last['description'] ?? ''));

        return match (true) {
            str_contains($text, 'deliver') || str_contains($text, 'terima'), str_contains($text, 'diterima') => 'delivered',
            str_contains($text, 'transit') || str_contains($text, 'dalam') => 'in_transit',
            str_contains($text, 'ship') || str_contains($text, 'kirim') => 'shipped',
            default => $current,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function returns(int $page = 1, string $status = '', string $search = ''): array
    {
        $query = OrderReturn::query()
            ->with(['order:id,order_number,customer_id,total', 'order.customer:id,name,email', 'orderItem:id,product_id,quantity,sub_total', 'orderItem.product:id,name,sku', 'decider:id,name']);

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('rma_number', 'like', '%'.$search.'%')
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', '%'.$search.'%'));
            });
        }

        $page = max(1, $page);
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('id')->forPage($page, self::PAGE_SIZE)->get()
            ->map(fn (OrderReturn $ret): array => [
                'id' => (int) $ret->id,
                'order_id' => (int) $ret->order_id,
                'rma_number' => (string) $ret->rma_number,
                'order_number' => (string) ($ret->order?->order_number ?? '-'),
                'customer' => (string) ($ret->order?->customer?->name ?? '-'),
                'product' => (string) ($ret->orderItem?->product?->name ?? 'Seluruh pesanan'),
                'reason' => (string) $ret->reason,
                'status' => (string) $ret->status,
                'status_label' => self::RETURN_STATUSES[$ret->status] ?? $ret->status,
                'amount' => (float) $ret->amount,
                'amount_formatted' => Currency::format((float) $ret->amount),
                'decided_at' => (string) ($ret->decided_at?->format('Y-m-d H:i') ?? ''),
                'decider' => (string) ($ret->decider?->name ?? ''),
                'created_at' => (string) ($ret->created_at?->format('Y-m-d H:i') ?? ''),
            ])
            ->all();

        $counts = ['all' => 0];
        foreach (array_keys(self::RETURN_STATUSES) as $statusKey) {
            $counts[$statusKey] = 0;
        }

        try {
            $counts['all'] = (int) OrderReturn::query()->count();
            foreach (OrderReturn::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->get() as $row) {
                $key = (string) $row->status;
                if (array_key_exists($key, $counts)) {
                    $counts[$key] = (int) $row->aggregate;
                }
            }
        } catch (\Throwable) {
            foreach ($counts as $key => $_) {
                $counts[$key] = 0;
            }
        }

        return [
            'rows' => $rows,
            'counts' => $counts,
            'statuses' => self::RETURN_STATUSES,
            'pending_amount' => (float) OrderReturn::query()->where('status', 'requested')->sum('amount'),
            'pagination' => [
                'total' => $total,
                'per_page' => self::PAGE_SIZE,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / self::PAGE_SIZE)),
            ],
        ];
    }

    /**
     * Record a return decision. A return can only be decided once.
     */
    public function decideReturn(OrderReturn $return, string $decision, ?string $note, float $amount, ?int $actorId): OrderReturn
    {
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            abort(422, 'Keputusan retur tidak valid.');
        }

        return DB::transaction(function () use ($return, $decision, $note, $amount, $actorId): OrderReturn {
            $locked = OrderReturn::query()->lockForUpdate()->findOrFail($return->id);

            if ($locked->status !== 'requested') {
                abort(422, 'Retur ini sudah diproses dengan status '.$locked->status.'.');
            }

            $before = $locked->only(['status', 'amount', 'decided_by', 'decided_at']);

            $locked->forceFill([
                'status' => $decision,
                'admin_note' => $note,
                'amount' => $decision === 'approved' ? max(0.0, $amount) : 0.0,
                'decided_by' => $actorId,
                'decided_at' => now(),
            ])->save();

            $order = Order::query()->find($locked->order_id);
            if ($order !== null) {
                $order->statusHistory()->create([
                    'status' => 'return_'.$decision,
                    'changed_by' => $actorId,
                    'note' => 'RMA '.$locked->rma_number.($note !== null && $note !== '' ? ': '.$note : ''),
                ]);
            }

            app(AuditLogger::class)->log('order_return.'.$decision, $locked, $before, $locked->only(['status', 'amount', 'decided_by']), $actorId);

            return $locked->refresh();
        });
    }

    // ── Logistik lanjutan (aditif): click & collect, manifest AWB, jemput retur ──

    public const PICKUP_CODE_LENGTH = 6;

    public const RETURN_PICKUP_STATUSES = [
        'scheduled' => 'Terjadwal',
        'in_transit' => 'Dijemput kurir',
        'received' => 'Diterima gudang',
        'cancelled' => 'Dibatalkan',
    ];

    /** Kode ambil 6 karakter tanpa huruf yang mudah tertukar (0/O, 1/I). */
    public static function generatePickupCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < self::PICKUP_CODE_LENGTH; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (OrderShipment::query()->where('pickup_code', $code)->whereNull('pickup_verified_at')->exists());

        return $code;
    }

    /**
     * Buat kiriman ambil di toko: tanpa ongkir, dengan kode ambil.
     * Satu transaksi atomik: kunci order, tulis shipment, tandai order.
     */
    public function createPickupShipment(Order $order, int $warehouseId, ?int $actorId = null): OrderShipment
    {
        return DB::transaction(function () use ($order, $warehouseId, $actorId): OrderShipment {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $warehouse = Warehouse::query()->whereKey($warehouseId)->where('is_active', true)->firstOrFail();

            if (\Illuminate\Support\Facades\Schema::hasColumn('warehouses', 'allow_pickup')
                && ! (bool) $warehouse->allow_pickup) {
                abort(422, 'Gudang '.$warehouse->name.' tidak melayani pengambilan di tempat.');
            }

            $code = self::generatePickupCode();

            $shipment = OrderShipment::query()->create([
                'order_id' => $locked->getKey(),
                'provider_id' => null,
                'courier' => 'PICKUP',
                'service' => 'Ambil di Toko',
                'tracking_number' => null,
                'label_url' => null,
                'weight' => null,
                'cost' => 0,
                'status' => 'pending',
                'tracking_history' => [[
                    'description' => 'Menunggu diambil di '.$warehouse->name.'. Kode ambil: '.$code,
                    'at' => now()->toDateTimeString(),
                ]],
                'warehouse_id' => $warehouse->id,
                'is_pickup' => true,
                'pickup_code' => $code,
            ]);

            $this->fillPickupOrder($locked, $warehouse->id, $code);

            $locked->statusHistory()->create([
                'status' => 'pickup_created',
                'changed_by' => $actorId,
                'note' => 'Ambil di toko '.$warehouse->name.' ('.$warehouse->code.'). Tanpa ongkir.',
            ]);

            app(AuditLogger::class)->log('order.pickup.created', $locked, [], [
                'shipment_id' => $shipment->getKey(),
                'warehouse_id' => $warehouse->id,
            ], $actorId);

            return $shipment->refresh();
        });
    }

    /** Tandai kiriman pickup siap diambil (status tetap dalam kosakata existing + penanda waktu). */
    public function readyForPickup(OrderShipment $shipment, ?int $actorId = null): OrderShipment
    {
        return DB::transaction(function () use ($shipment, $actorId): OrderShipment {
            $locked = OrderShipment::query()->lockForUpdate()->findOrFail($shipment->getKey());

            abort_if(! (bool) $locked->is_pickup, 422, 'Kiriman ini bukan ambil di toko.');

            $locked->forceFill([
                'status' => 'shipped',
                'shipped_at' => $locked->shipped_at ?? now(),
                'tracking_history' => array_merge(
                    is_array($locked->tracking_history) ? $locked->tracking_history : [],
                    [['description' => 'Siap diambil. Tunjukkan kode ambil kepada petugas.', 'at' => now()->toDateTimeString()]],
                ),
            ])->save();

            $order = Order::query()->lockForUpdate()->find($locked->order_id);
            if ($order !== null && \Illuminate\Support\Facades\Schema::hasColumn('orders', 'pickup_ready_at')) {
                $order->forceFill(['pickup_ready_at' => now()])->save();
            }

            app(AuditLogger::class)->log('order.pickup.ready', $locked, [], ['order_id' => $locked->order_id], $actorId);

            return $locked->refresh();
        });
    }

    /**
     * Verifikasi kode ambil. Kode benar tepat satu kali: upaya memakai hash
     * pada orders.pickup_code_hash, fallback ke pickup_code shipment.
     */
    public function verifyPickup(Order $order, string $code, ?int $actorId = null): OrderShipment
    {
        $code = strtoupper(trim($code));

        return DB::transaction(function () use ($order, $code, $actorId): OrderShipment {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            $shipment = OrderShipment::query()->lockForUpdate()
                ->where('order_id', $lockedOrder->getKey())
                ->where('is_pickup', true)
                ->orderByDesc('id')
                ->firstOrFail();

            if ($shipment->pickup_verified_at !== null) {
                abort(422, 'Kode ambil ini sudah dipakai.');
            }

            $hash = (string) ($lockedOrder->getAttribute('pickup_code_hash') ?? '');
            $valid = ($hash !== '' && \Illuminate\Support\Facades\Hash::check($code, $hash))
                || ($shipment->pickup_code !== null && hash_equals((string) $shipment->pickup_code, $code));

            if (! $valid) {
                abort(422, 'Kode ambil tidak cocok. Periksa kembali kode pada pesanan.');
            }

            $shipment->forceFill([
                'status' => 'delivered',
                'delivered_at' => now(),
                'pickup_verified_at' => now(),
                'tracking_history' => array_merge(
                    is_array($shipment->tracking_history) ? $shipment->tracking_history : [],
                    [['description' => 'Diambil pelanggan dengan kode terverifikasi.', 'at' => now()->toDateTimeString()]],
                ),
            ])->save();

            if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'pickup_verified_at')) {
                $lockedOrder->forceFill([
                    'pickup_verified_at' => now(),
                    'pickup_completed_at' => now(),
                ])->save();
            }

            $lockedOrder->statusHistory()->create([
                'status' => 'pickup_completed',
                'changed_by' => $actorId,
                'note' => 'Pesanan diambil pelanggan dengan kode terverifikasi.',
            ]);

            app(AuditLogger::class)->log('order.pickup.verified', $shipment, [], ['order_id' => $lockedOrder->getKey()], $actorId);

            return $shipment->refresh();
        });
    }

    /**
     * Batch manifest AWB: beri nomor manifest + tanggal pada kiriman pilihan.
     * Idempoten per baris: kiriman yang sudah bermanifest tidak diubah.
     *
     * @param  list<int>  $shipmentIds
     * @return array{manifest_no: string, manifest_date: string, total: int}
     */
    public function manifestBatch(array $shipmentIds, ?int $actorId = null): array
    {
        return DB::transaction(function () use ($shipmentIds, $actorId): array {
            $ids = array_values(array_unique(array_map('intval', $shipmentIds)));
            $ids = array_filter($ids, fn (int $id): bool => $id > 0);

            if ($ids === []) {
                abort(422, 'Pilih minimal satu kiriman untuk manifest.');
            }

            do {
                $manifestNo = 'MNF-'.now()->format('Ymd').'-'.strtoupper(\Illuminate\Support\Str::random(6));
            } while (OrderShipment::query()->where('manifest_no', $manifestNo)->exists());

            $today = now()->toDateString();
            $count = 0;

            foreach (OrderShipment::query()->whereIn('id', $ids)->lockForUpdate()->get() as $shipment) {
                if ($shipment->manifest_no !== null && $shipment->manifest_no !== '') {
                    continue;
                }

                $shipment->forceFill(['manifest_no' => $manifestNo, 'manifest_date' => $today])->save();
                $count++;
            }

            app(AuditLogger::class)->log('shipment.manifest.created', null, [], [
                'manifest_no' => $manifestNo,
                'total' => $count,
            ], $actorId);

            return ['manifest_no' => $manifestNo, 'manifest_date' => $today, 'total' => $count];
        });
    }

    /** Rekap manifest per kurir per hari (delegasi ke ShippingService). */
    public function manifestRecap(?string $courier = null, ?string $date = null): array
    {
        return $this->shipping->manifestRecap($courier, $date);
    }

    /** Baris ekspor manifest (header + rows). */
    public function manifestExportRows(string $manifestNo): array
    {
        return $this->shipping->manifestExportRows($manifestNo);
    }

    /**
     * Jadwalkan penjemputan retur oleh kurir. Retur harus sudah disetujui.
     *
     * @param  array{scheduled_at?: string, address?: string, courier?: string}  $data
     */
    public function scheduleReturnPickup(\App\Models\OrderReturn $return, array $data, ?int $actorId = null): \App\Models\OrderReturn
    {
        return DB::transaction(function () use ($return, $data, $actorId): \App\Models\OrderReturn {
            $locked = \App\Models\OrderReturn::query()->lockForUpdate()->findOrFail($return->getKey());

            if (! in_array((string) $locked->status, ['approved', 'received'], true)) {
                abort(422, 'Penjemputan hanya dapat dijadwalkan untuk retur yang disetujui.');
            }

            $scheduledAt = isset($data['scheduled_at']) && is_string($data['scheduled_at']) && trim($data['scheduled_at']) !== ''
                ? trim($data['scheduled_at'])
                : now()->addDay()->toDateTimeString();

            try {
                $scheduledAt = \Carbon\Carbon::parse($scheduledAt)->toDateTimeString();
            } catch (\Throwable) {
                abort(422, 'Jadwal penjemputan tidak valid.');
            }

            $before = $locked->only(['pickup_status', 'pickup_scheduled_at']);

            $locked->forceFill([
                'pickup_status' => 'scheduled',
                'pickup_scheduled_at' => $scheduledAt,
                'pickup_address' => isset($data['address']) && is_string($data['address']) && trim($data['address']) !== ''
                    ? mb_substr(trim($data['address']), 0, 255)
                    : $locked->pickup_address,
                'pickup_courier' => isset($data['courier']) && is_string($data['courier']) && trim($data['courier']) !== ''
                    ? mb_substr(trim($data['courier']), 0, 40)
                    : $locked->pickup_courier,
                'return_label_code' => $locked->return_label_code ?: $this->nextReturnLabel(),
            ])->save();

            app(AuditLogger::class)->log('order_return.pickup.scheduled', $locked, $before, $locked->only(['pickup_status', 'pickup_scheduled_at']), $actorId);

            return $locked->refresh();
        });
    }

    /** Ubah status penjemputan retur: scheduled → in_transit → received. */
    public function markReturnPickup(\App\Models\OrderReturn $return, string $status, ?string $tracking = null, ?int $actorId = null): \App\Models\OrderReturn
    {
        if (! array_key_exists($status, self::RETURN_PICKUP_STATUSES)) {
            abort(422, 'Status penjemputan retur tidak valid.');
        }

        return DB::transaction(function () use ($return, $status, $tracking, $actorId): \App\Models\OrderReturn {
            $locked = \App\Models\OrderReturn::query()->lockForUpdate()->findOrFail($return->getKey());
            $current = (string) ($locked->pickup_status ?? '');

            $allowed = [
                '' => ['scheduled', 'cancelled'],
                'scheduled' => ['in_transit', 'cancelled'],
                'in_transit' => ['received', 'cancelled'],
            ];

            if (! in_array($status, $allowed[$current] ?? [], true)) {
                abort(422, 'Penjemputan retur tidak dapat berubah dari '.$current.' ke '.$status.'.');
            }

            $before = $locked->only(['pickup_status', 'pickup_tracking']);

            $locked->forceFill(array_filter([
                'pickup_status' => $status,
                'pickup_tracking' => $tracking !== null && trim($tracking) !== '' ? mb_substr(trim($tracking), 0, 80) : $locked->pickup_tracking,
            ], fn ($value): bool => $value !== null))->save();

            if ($status === 'received') {
                $locked->forceFill(['status' => 'received'])->save();
            }

            app(AuditLogger::class)->log('order_return.pickup.'.$status, $locked, $before, $locked->only(['pickup_status', 'pickup_tracking']), $actorId);

            return $locked->refresh();
        });
    }

    /** Data label retur untuk dicetak (kode label + jadwal + alamat). */
    public function returnLabelData(\App\Models\OrderReturn $return): array
    {
        $return->loadMissing(['order:id,order_number,customer_id', 'order.customer:id,name,phone', 'orderItem.product:id,name,sku']);

        $scheduledAt = $return->pickup_scheduled_at;

        if (is_string($scheduledAt) && trim($scheduledAt) !== '') {
            try {
                $scheduledAt = \Carbon\Carbon::parse($scheduledAt)->format('Y-m-d H:i');
            } catch (\Throwable) {
                $scheduledAt = $scheduledAt;
            }
        } elseif ($scheduledAt instanceof \DateTimeInterface) {
            $scheduledAt = $scheduledAt->format('Y-m-d H:i');
        } else {
            $scheduledAt = '';
        }

        return [
            'kode_label' => (string) ($return->return_label_code ?? '-'),
            'rma' => (string) $return->rma_number,
            'pesanan' => (string) ($return->order?->order_number ?? '-'),
            'pelanggan' => (string) ($return->order?->customer?->name ?? '-'),
            'produk' => (string) ($return->orderItem?->product?->name ?? 'Seluruh pesanan'),
            'kurir_jemput' => (string) ($return->pickup_courier ?? '-'),
            'jadwal_jemput' => (string) $scheduledAt,
            'alamat_jemput' => (string) ($return->pickup_address ?? ''),
            'status_jemput' => self::RETURN_PICKUP_STATUSES[$return->pickup_status ?? ''] ?? '-',
        ];
    }

    private function nextReturnLabel(): string
    {
        do {
            $code = 'RTL-'.now()->format('Ymd').'-'.strtoupper(\Illuminate\Support\Str::random(6));
        } while (\App\Models\OrderReturn::query()->where('return_label_code', $code)->exists());

        return $code;
    }

    private function fillPickupOrder(Order $order, int $warehouseId, string $code): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('orders', 'is_pickup')) {
            return;
        }

        $order->forceFill([
            'is_pickup' => true,
            'warehouse_id' => $warehouseId,
            'pickup_warehouse_id' => $warehouseId,
            'pickup_code_hash' => \Illuminate\Support\Facades\Hash::make($code),
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function orderSummary(Order $order): array
    {
        return [
            'order_number' => (string) $order->order_number,
            'warehouse' => (string) (Warehouse::query()->find($order->warehouse_id)?->name ?? '-'),
            'shipments' => (int) $order->shipments()->count(),
            'returns' => (int) $order->returns()->count(),
        ];
    }

    // ── Pendalaman RMA: QC → resolusi (delegasi, aditif) ──

    /**
     * Alur penuh: grade QC lalu putus resolusi (refund/replace/exchange).
     * Idempoten di kedua langkah; refund dana memakai fee dari pengaturan.
     *
     * @return array{resolution:string, breakdown:array<string,float>, rma:OrderReturn}
     */
    public function qcResolve(
        OrderReturn $retur,
        string $grade,
        string $resolution,
        ?int $actorId = null,
        ?string $note = null,
        ?int $quantity = null,
    ): array {
        $graded = app(\App\Services\RefundWorkflowService::class)
            ->gradeReturn($retur, $grade, $actorId, $note, $quantity);

        $fee = (float) (\App\Models\SystemSetting::get('restocking_fee_percent', '0') ?: 0);

        return app(\App\Services\RefundWorkflowService::class)
            ->resolveReturn($graded, $resolution, $actorId, $note, $fee);
    }
}
