<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Events\Support\OrderSnapshot;
use App\Models\OrderReturn;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ReturnRequested implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'return.requested';

    public const ENTITY = 'return';

    public function __construct(public OrderReturn $return, public ?int $actorId = null) {}

    public function entityId(): ?string
    {
        return (string) $this->return->getKey();
    }

    public function shopId(): ?int
    {
        return $this->return->order?->shop_id;
    }

    public function toPayload(): array
    {
        $return = $this->return->loadMissing(['order', 'orderItem.product']);

        return [
            'return' => [
                'id' => (int) $return->getKey(),
                'rma_number' => (string) $return->rma_number,
                'status' => (string) $return->status,
                'reason' => (string) $return->reason,
                'description' => (string) $return->description,
                'amount' => (string) $return->amount,
                'order_id' => $return->order_id,
                'order_number' => $return->order?->order_number,
                'order_item_id' => $return->order_item_id,
                'product_id' => $return->orderItem?->product_id,
                'product_name' => $return->orderItem?->product?->name,
                'quantity' => (int) ($return->orderItem?->quantity ?? 0),
                'decided_at' => OrderSnapshot::iso($return->decided_at),
                'created_at' => OrderSnapshot::iso($return->created_at),
            ],
            'actor_id' => $this->actorId,
        ];
    }
}
