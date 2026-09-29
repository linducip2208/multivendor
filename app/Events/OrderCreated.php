<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Events\Support\ActorSnapshot;
use App\Events\Support\OrderSnapshot;
use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OrderCreated implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'order.created';

    public const ENTITY = 'order';

    public function __construct(public Order $order) {}

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
        $order = $this->order->loadMissing(['items.product', 'shop', 'customer']);

        return [
            'order' => OrderSnapshot::summary($order),
            'items' => OrderSnapshot::items($order),
            'recipient' => OrderSnapshot::recipient($order),
            'shop' => ActorSnapshot::shop($order->shop),
            'customer' => ActorSnapshot::user($order->customer),
        ];
    }
}
