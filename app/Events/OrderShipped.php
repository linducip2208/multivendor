<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Events\Support\OrderSnapshot;
use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OrderShipped implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'order.shipped';

    public const ENTITY = 'order';

    public function __construct(
        public Order $order,
        public ?string $trackingNumber = null,
        public ?string $courier = null,
        public ?int $actorId = null,
    ) {}

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
        $order = $this->order->loadMissing(['shipments', 'items.product']);

        return [
            'order' => OrderSnapshot::summary($order),
            'items' => OrderSnapshot::items($order),
            'shipping' => [
                'tracking_number' => $this->trackingNumber ?? $order->shipping_tracking_id,
                'courier' => $this->courier ?? $order->shipping_service,
                'service' => $order->shipping_service,
                'shipped_at' => OrderSnapshot::iso($order->shipped_at),
            ],
            'recipient' => OrderSnapshot::recipient($order),
            'actor_id' => $this->actorId,
        ];
    }
}
