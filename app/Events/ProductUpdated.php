<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Events\Support\ProductSnapshot;
use App\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ProductUpdated implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'product.updated';

    public const ENTITY = 'product';

    public function __construct(public Product $product, public array $changed = []) {}

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
        return [
            'product' => ProductSnapshot::summary($this->product->loadMissing(['shop'])),
            'changed' => array_values($this->changed),
        ];
    }
}
