<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Events\Support\OrderSnapshot;
use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OrderPaid implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'order.paid';

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
        $order = $this->order->loadMissing(['paymentGroup', 'items.product']);

        return [
            'order' => OrderSnapshot::summary($order),
            'items' => OrderSnapshot::items($order),
            'payment' => [
                'payment_group_id' => $order->payment_group_id,
                'payment_number' => $order->paymentGroup?->payment_number,
                'status' => $order->paymentGroup?->status,
                'grand_total' => $order->paymentGroup?->grand_total,
                'currency' => (string) ($order->currency ?: 'IDR'),
                'paid_at' => OrderSnapshot::iso($order->paymentGroup?->paid_at),
            ],
        ];
    }
}
