<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Events\Support\OrderSnapshot;
use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OrderDelivered implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'order.delivered';

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
        $order = $this->order->loadMissing(['items.product', 'deliveryMan']);

        return [
            'order' => OrderSnapshot::summary($order),
            'items' => OrderSnapshot::items($order),
            'delivered_at' => OrderSnapshot::iso($order->delivered_at),
            'delivery_man_id' => $order->delivery_man_id,
            'actor_id' => $this->actorId,
        ];
    }
}
