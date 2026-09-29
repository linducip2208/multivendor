<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Events\Support\OrderSnapshot;
use App\Models\PaymentGroup;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PaymentCreated implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'payment.created';

    public const ENTITY = 'payment';

    public function __construct(public PaymentGroup $payment) {}

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
        $payment = $this->payment->loadMissing(['provider', 'orders']);

        return [
            'payment' => self::snapshot($payment),
            'order_count' => $payment->orders->count(),
            'order_ids' => $payment->orders->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        ];
    }

    /** @return array<string, mixed> */
    public static function snapshot(PaymentGroup $payment): array
    {
        return [
            'id' => (int) $payment->getKey(),
            'payment_number' => (string) $payment->payment_number,
            'status' => (string) $payment->status,
            'provider' => $payment->provider?->name,
            'provider_type' => $payment->provider?->type,
            'gateway_reference' => $payment->gateway_reference,
            'subtotal' => (string) $payment->subtotal,
            'tax' => (string) $payment->tax,
            'shipping_cost' => (string) $payment->shipping_cost,
            'discount' => (string) $payment->discount,
            'grand_total' => (string) $payment->grand_total,
            'customer_id' => $payment->customer_id,
            'created_at' => OrderSnapshot::iso($payment->created_at),
            'paid_at' => OrderSnapshot::iso($payment->paid_at),
            'expired_at' => OrderSnapshot::iso($payment->expired_at),
        ];
    }
}
