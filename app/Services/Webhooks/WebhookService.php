<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Jobs\DispatchWebhookDelivery;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

final class WebhookService
{
    public const QUEUE = 'webhooks';

    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXHAUSTED = 'exhausted';

    /** @var list<int> Exponential backoff in seconds, indexed by completed attempt. */
    public const BACKOFF = [60, 300, 1800, 7200, 43200];

    /** @var list<int> 4xx codes that are worth retrying. */
    public const RETRYABLE_CLIENT_STATUSES = [408, 409, 429];

    public function __construct(private readonly PayloadRedactor $redactor) {}

    /**
     * Fan an event out to every active endpoint subscribed to it and queue one
     * delivery job per endpoint.
     *
     * @param  array<string, mixed>  $payload
     * @return int number of deliveries queued
     */
    public function dispatch(
        string $event,
        array $payload,
        ?string $eventId = null,
        ?int $shopId = null,
        ?int $tenantId = null,
    ): int {
        $event = trim($event);

        if ($event === '') {
            return 0;
        }

        $endpoints = $this->targets($event, $shopId, $tenantId);

        if ($endpoints->isEmpty()) {
            return 0;
        }

        $eventId ??= (string) Str::uuid();
        $body = $this->redactor->redact($payload);
        $maxAttempts = count(self::BACKOFF);
        $queued = 0;

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::create([
                'webhook_endpoint_id' => $endpoint->getKey(),
                'event' => $event,
                'event_id' => $eventId,
                'payload' => $body,
                'attempt' => 1,
                'max_attempts' => $maxAttempts,
                'status' => self::STATUS_PENDING,
                'next_retry_at' => now(),
            ]);

            $endpoint->forceFill(['last_triggered_at' => now()])->save();

            DispatchWebhookDelivery::dispatch((int) $delivery->getKey())
                ->onQueue(self::QUEUE)
                ->afterCommit();

            $queued++;
        }

        return $queued;
    }

    /**
     * @return \Illuminate\Support\Collection<int, WebhookEndpoint>
     */
    public function targets(string $event, ?int $shopId = null, ?int $tenantId = null)
    {
        return WebhookEndpoint::query()
            ->active()
            ->when($shopId !== null, fn ($query) => $query->where(fn ($inner) => $inner->whereNull('shop_id')->orWhere('shop_id', $shopId)))
            ->when($tenantId !== null, fn ($query) => $query->where(fn ($inner) => $inner->whereNull('tenant_id')->orWhere('tenant_id', $tenantId)))
            ->orderBy('id')
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint): bool => $endpoint->subscribesTo($event))
            ->values();
    }

    public function backoffFor(int $attempt): int
    {
        $index = max(0, $attempt - 1);

        return self::BACKOFF[$index] ?? self::BACKOFF[count(self::BACKOFF) - 1];
    }

    public function shouldRetry(?int $responseStatus, int $attempt, int $maxAttempts): bool
    {
        if ($attempt >= $maxAttempts) {
            return false;
        }

        if ($responseStatus === null) {
            return true;
        }

        if ($responseStatus >= 200 && $responseStatus < 300) {
            return false;
        }

        if (in_array($responseStatus, self::RETRYABLE_CLIENT_STATUSES, true)) {
            return true;
        }

        return $responseStatus >= 500 || $responseStatus === 429;
    }
}
