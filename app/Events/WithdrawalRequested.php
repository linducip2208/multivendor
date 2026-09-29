<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\BuildsWebhookPayload;
use App\Events\Contracts\WebhookPayload;
use App\Models\VendorWithdrawRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class WithdrawalRequested implements WebhookPayload
{
    use BuildsWebhookPayload;
    use Dispatchable;
    use SerializesModels;

    public const EVENT = 'withdrawal.requested';

    public const ENTITY = 'withdrawal';

    public function __construct(public VendorWithdrawRequest $withdraw) {}

    public function entityId(): ?string
    {
        return (string) $this->withdraw->getKey();
    }

    public function shopId(): ?int
    {
        return $this->withdraw->shop_id;
    }

    public function toPayload(): array
    {
        return [
            'withdrawal' => WithdrawalSnapshot::summary($this->withdraw, false),
        ];
    }
}
