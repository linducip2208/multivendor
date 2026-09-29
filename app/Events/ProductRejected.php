<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Events\Support\ProductSnapshot;
use App\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ProductRejected implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'product.rejected';

    public const ENTITY = 'product';

    public function __construct(public Product $product, public ?string $reason = null, public ?int $actorId = null) {}

    public function entityId(): ?string
    {
        return (string) $this->product->getKey();
    }

    public function shopId(): ?int
    {
        return $this->product->shop_id;
    }

    public function toPayload(): array
    {
        $product = $this->product->loadMissing('shop');

        return [
            'product' => ProductSnapshot::summary($product),
            'reason' => $this->reason,
            'request_status' => $product->request_status,
            'rejected_at' => now()->toIso8601String(),
            'actor_id' => $this->actorId,
        ];
    }
}
