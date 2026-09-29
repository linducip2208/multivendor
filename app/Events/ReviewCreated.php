<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Models\ProductReview;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ReviewCreated implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'review.created';

    public const ENTITY = 'review';

    public function __construct(public ProductReview $review) {}

    public function entityId(): ?string
    {
        return (string) $this->review->getKey();
    }

    public function shopId(): ?int
    {
        return $this->review->product?->shop_id;
    }

    public function toPayload(): array
    {
        $review = $this->review->loadMissing(['product', 'customer']);

        return [
            'review' => [
                'id' => (int) $review->getKey(),
                'product_id' => $review->product_id,
                'product_name' => $review->product?->name,
                'customer_id' => $review->customer_id,
                'customer_name' => $review->customer?->name,
                'rating' => (int) $review->rating,
                'comment' => (string) $review->comment,
                'status' => (bool) $review->status,
                'created_at' => $review->created_at?->toIso8601String(),
            ],
        ];
    }
}
