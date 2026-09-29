<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Events\Support\OrderSnapshot;
use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OrderCancelled implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'order.cancelled';

    public const ENTITY = 'order';

    public function __construct(public Order $order, public ?string $reason = null, public ?int $actorId = null) {}

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
        $order = $this->order->loadMissing(['items.product']);

        return [
            'order' => OrderSnapshot::summary($order),
            'items' => OrderSnapshot::items($order),
            'reason' => $this->reason ?? $order->cancel_reason,
            'cancelled_at' => OrderSnapshot::iso($order->canceled_at),
            'stock_released' => $order->stock_released_at !== null,
            'actor_id' => $this->actorId,
        ];
    }
}
