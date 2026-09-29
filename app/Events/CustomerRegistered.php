<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Events\Support\ActorSnapshot;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CustomerRegistered implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'customer.registered';

    public const ENTITY = 'user';

    public function __construct(public User $customer) {}

    public function entityId(): ?string
    {
        return (string) $this->customer->getKey();
    }

    public function shopId(): ?int
    {
        return null;
    }

    public function toPayload(): array
    {
        return [
            'customer' => ActorSnapshot::user($this->customer),
            'referral_code' => $this->customer->referral_code,
            'referred_by' => $this->customer->referred_by,
            'registered_at' => $this->customer->created_at?->toIso8601String(),
        ];
    }
}
