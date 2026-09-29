<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\OrderItem;
use App\Services\Payment\PaymentLog;
use App\Services\RefundWorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExecuteRefund implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 120;

    public int $timeout = 120;

    public function __construct(public int $orderItemId, public float|int|string|null $amount = null, public ?int $actorId = null) {}

    public function afterCommit(): bool
    {
        return true;
    }

    public function handle(RefundWorkflowService $refunds): void
    {
        $item = OrderItem::find($this->orderItemId);

        if (! $item) {
            return;
        }

        $refunds->execute($item, $this->amount, $this->actorId);
    }

    public function failed(?Throwable $exception): void
    {
        PaymentLog::channel('error', 'Refund job exhausted its retries', [
            'order_item_id' => $this->orderItemId,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
