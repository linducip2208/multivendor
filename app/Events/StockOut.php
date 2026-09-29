<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class StockOut implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'product.stock_out';

    public const ENTITY = 'product';

    public function __construct(public Product $product) {}

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
            'product_id' => (int) $this->product->getKey(),
            'name' => (string) $this->product->name,
            'sku' => (string) $this->product->sku,
            'shop_id' => $this->product->shop_id,
            'remaining' => 0,
            'detected_at' => now()->toIso8601String(),
        ];
    }
}
