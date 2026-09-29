<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Models\PaymentGroup;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PaymentPaid implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'payment.paid';

    public const ENTITY = 'payment';

    public function __construct(public PaymentGroup $payment, public ?string $gatewayReference = null) {}

    public function entityId(): ?string
    {
        return (string) $this->payment->getKey();
    }

    public function shopId(): ?int
    {
        return null;
    }

    public function toPayload(): array
    {
        $payment = $this->payment->loadMissing(['orders']);

        return [
            'payment' => array_merge(PaymentCreated::snapshot($payment), [
                'gateway_reference' => $this->gatewayReference ?? $payment->gateway_reference,
            ]),
            'orders' => $payment->orders->map(fn ($order): array => [
                'id' => (int) $order->getKey(),
                'order_number' => (string) $order->order_number,
                'shop_id' => $order->shop_id,
                'total' => (string) $order->total,
            ])->values()->all(),
        ];
    }
}
