<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendOrderNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public int $timeout = 60;

    public function __construct(public int $orderId, public string $event) {}

    public function afterCommit(): bool
    {
        return true;
    }

    public function handle(NotificationService $notifications): void
    {
        try {
            $order = Order::with(['customer', 'shop.vendor', 'deliveryMan'])->find($this->orderId);

            if (! $order) {
                return;
            }

            $notifications->sendForEvent($order, $this->event);
        } catch (Throwable $e) {
            $notifications->logJobFailure($this->event, $this->orderId, $e);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(NotificationService::class)->logJobFailure($this->event, $this->orderId, $exception);
    }
}
