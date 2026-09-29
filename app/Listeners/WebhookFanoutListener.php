<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Contracts\WebhookPayload;
use App\Services\Webhooks\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Single outbound entry point: turns every domain event into webhook fan-out.
 *
 * Queued and after-commit so the payload is rebuilt from committed rows and a
 * rolled back transaction never produces a delivery.
 */
class WebhookFanoutListener implements ShouldQueue, ShouldQueueAfterCommit
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 120;

    public function viaQueue(): string
    {
        return WebhookService::QUEUE;
    }

    public function handle(WebhookPayload $event): void
    {
        $webhooks = app(WebhookService::class);

        try {
            $webhooks->dispatch(
                $event->eventName(),
                $event->envelope(),
                $event->eventId(),
                $this->shopIdOf($event),
            );
        } catch (Throwable $e) {
            Log::error('webhook.fanout_failed', [
                'event' => $event->eventName(),
                'entity_type' => $event->entityType(),
                'entity_id' => $event->entityId(),
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function shopIdOf(WebhookPayload $event): ?int
    {
        return method_exists($event, 'shopId') ? $event->shopId() : null;
    }
}
