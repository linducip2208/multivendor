<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Events\Support\ActorSnapshot;
use App\Models\Shop;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewVendorRegistered implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'vendor.registered';

    public const ENTITY = 'shop';

    public function __construct(public Shop $shop) {}

    public function entityId(): ?string
    {
        return $this->shop->getKey() === null ? null : (string) $this->shop->getKey();
    }

    public function shopId(): ?int
    {
        return $this->shop->getKey() === null ? null : (int) $this->shop->getKey();
    }

    public function toPayload(): array
    {
        $shop = $this->shop->loadMissing('vendor');

        return [
            'shop' => ActorSnapshot::shop($shop),
            'vendor' => ActorSnapshot::user($shop->vendor),
            'registered_at' => $shop->created_at?->toIso8601String(),
        ];
    }
}
