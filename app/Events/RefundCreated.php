<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Events\Support\OrderSnapshot;
use App\Models\Refund;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RefundCreated implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'refund.created';

    public const ENTITY = 'refund';

    public function __construct(public Refund $refund) {}

    public function entityId(): ?string
    {
        return (string) $this->refund->getKey();
    }

    public function shopId(): ?int
    {
        return $this->refund->order?->shop_id;
    }

    public function toPayload(): array
    {
        $refund = $this->refund->loadMissing(['order', 'orderItem.product']);

        return [
            'refund' => [
                'id' => (int) $refund->getKey(),
                'refund_number' => (string) $refund->refund_number,
                'status' => (string) $refund->status,
                'amount' => (string) $refund->amount,
                'currency' => (string) $refund->currency,
                'reason' => (string) $refund->reason,
                'order_id' => $refund->order_id,
                'order_number' => $refund->order?->order_number,
                'order_item_id' => $refund->order_item_id,
                'product_name' => $refund->orderItem?->product?->name,
                'payment_group_id' => $refund->payment_group_id,
                'requested_by_type' => (string) $refund->requested_by_type,
                'requested_by' => $refund->requested_by,
                'created_at' => OrderSnapshot::iso($refund->created_at),
            ],
        ];
    }
}
