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
}
