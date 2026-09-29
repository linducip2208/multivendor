<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Events\Support\OrderSnapshot;
use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OrderPacked implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'order.packed';

    public const ENTITY = 'order';

    public function __construct(public Order $order, public ?int $actorId = null) {}

    public function entityId(): ?string
    {
        return (string) $this->order->getKey();
    }

    public function shopId(): ?int
    {
        return $this->order->shop_id;
    }

    public function toPayload(): array
    {
        $order = $this->order->loadMissing(['items.product', 'shipments']);

        return [
            'order' => OrderSnapshot::summary($order),
            'items' => OrderSnapshot::items($order),
            'shipments' => $order->shipments->map(fn ($shipment): array => [
                'id' => (int) $shipment->getKey(),
                'carrier' => $shipment->carrier,
                'tracking_number' => $shipment->tracking_number,
                'status' => $shipment->status,
            ])->values()->all(),
            'actor_id' => $this->actorId,
        ];
    }
}
