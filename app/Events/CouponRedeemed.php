<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Models\CouponUsage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CouponRedeemed implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'coupon.redeemed';

    public const ENTITY = 'coupon_usage';

    public function __construct(public CouponUsage $usage) {}

    public function entityId(): ?string
    {
        return (string) $this->usage->getKey();
    }

    public function shopId(): ?int
    {
        return $this->usage->coupon?->shop_id ?? $this->usage->order?->shop_id;
    }

    public function toPayload(): array
    {
        $usage = $this->usage->loadMissing(['coupon', 'order', 'customer']);

        return [
            'coupon' => [
                'id' => $usage->coupon_id,
                'code' => $usage->coupon?->code,
                'type' => $usage->coupon?->coupon_type,
                'shop_id' => $usage->coupon?->shop_id,
            ],
            'redemption' => [
                'id' => (int) $usage->getKey(),
                'customer_id' => $usage->customer_id,
                'order_id' => $usage->order_id,
                'order_number' => $usage->order?->order_number,
                'discount_amount' => (string) $usage->discount_amount,
                'redeemed_at' => $usage->created_at?->toIso8601String(),
            ],
        ];
    }
}
