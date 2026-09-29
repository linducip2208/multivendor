<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Events\Support\ActorSnapshot;
use App\Models\Shop;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class VendorApproved implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'vendor.approved';

    public const ENTITY = 'shop';

    public function __construct(public Shop $shop, public ?int $actorId = null) {}

    public function entityId(): ?string
    {
        return (string) $this->shop->getKey();
    }

    public function shopId(): ?int
    {
        return (int) $this->shop->getKey();
    }

    public function toPayload(): array
    {
        $shop = $this->shop->loadMissing('vendor');

        return [
            'shop' => ActorSnapshot::shop($shop),
            'vendor' => ActorSnapshot::user($shop->vendor),
            'commission' => [
                'type' => (string) $shop->commission_type,
                'value' => (string) $shop->commission_value,
            ],
            'approved_at' => $shop->updated_at?->toIso8601String(),
            'actor_id' => $this->actorId,
        ];
    }
}
