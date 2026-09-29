<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\PayloadRedactor;
use App\Services\Webhooks\WebhookService;
use App\Services\Webhooks\WebhookSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchWebhookDelivery implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public int $deliveryId) {}

    public function afterCommit(): bool
    {
        return true;
    }

    public function handle(WebhookService $webhooks, WebhookSigner $signer, PayloadRedactor $redactor): void
    {
        $delivery = WebhookDelivery::query()->with('endpoint')->find($this->deliveryId);

        if (! $delivery) {
            return;
        }

        if ($delivery->status === WebhookService::STATUS_DELIVERED) {
            return;
        }

        $endpoint = $delivery->endpoint;

        if (! $endpoint instanceof WebhookEndpoint) {
            $this->settle($delivery, ['status' => WebhookService::STATUS_FAILED]);

            return;
        }

        if (! $endpoint->is_active) {
            $this->settle($delivery, [
                'status' => WebhookService::STATUS_FAILED,
                'response_body' => 'Endpoint nonaktif.',
            ]);

            return;
        }

        $payload = is_array($delivery->payload) ? $delivery->payload : [];
        $payload = $redactor->redact($payload);
        $rawBody = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($rawBody === '') {
            $this->settle($delivery, [
                'status' => WebhookService::STATUS_FAILED,
                'response_body' => 'Payload tidak dapat diserialisasi.',
            ]);

            return;
        }

        $attempt = (int) $delivery->attempt;
        $headers = $signer->headers($rawBody, (string) $endpoint->secret, [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'Multivendor-Webhook/1.0',
            WebhookSigner::EVENT_HEADER => (string) $delivery->event,
            WebhookSigner::ID_HEADER => (string) $delivery->event_id,
            WebhookSigner::DELIVERY_HEADER => (string) $delivery->getKey(),
            WebhookSigner::ATTEMPT_HEADER => (string) $attempt,
        ]);

        $status = null;
        $body = '';
        $durationMs = 0;
        $startedAt = microtime(true);

        try {
            $response = Http::withHeaders($headers)
                ->withOptions(['allow_redirects' => false])
                ->connectTimeout(5)
                ->timeout(10)
                ->withBody($rawBody, 'application/json')
                ->post((string) $endpoint->url);

            $status = $response->status();
            $body = $response->body();
        } catch (Throwable $e) {
            $body = 'Tidak dapat menghubungi endpoint: '.$e->getMessage();
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $this->settle($delivery, [
            'response_status' => $status,
            'response_body' => $this->truncate($body),
            'duration_ms' => $durationMs,
            'request_headers' => $headers,
        ]);

        $this->resolveOutcome($delivery->fresh() ?? $delivery, $endpoint, $status, $webhooks);
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);

        if (! $delivery) {
            return;
        }

        $this->settle($delivery, [
            'status' => WebhookService::STATUS_EXHAUSTED,
            'response_body' => $this->truncate('Job gagal: '.($exception?->getMessage() ?? 'tidak diketahui')),
        ]);
    }

    private function resolveOutcome(
        WebhookDelivery $delivery,
        WebhookEndpoint $endpoint,
        ?int $status,
        WebhookService $webhooks,
    ): void {
        $attempt = (int) $delivery->attempt;
        $maxAttempts = (int) $delivery->max_attempts;

        if ($status !== null && $status >= 200 && $status < 300) {
            $this->settle($delivery, [
                'status' => WebhookService::STATUS_DELIVERED,
                'delivered_at' => now(),
                'next_retry_at' => null,
            ]);

            $endpoint->forceFill(['failure_count' => 0])->save();

            return;
        }

        $endpoint->increment('failure_count');

        if ($status === 410) {
            $endpoint->forceFill(['is_active' => false])->save();

            $this->settle($delivery, [
                'status' => WebhookService::STATUS_FAILED,
                'next_retry_at' => null,
            ]);

            Log::warning('Webhook endpoint disabled after HTTP 410', [
                'webhook_endpoint_id' => $endpoint->getKey(),
                'webhook_delivery_id' => $delivery->getKey(),
            ]);

            return;
        }

        if (! $webhooks->shouldRetry($status, $attempt, $maxAttempts)) {
            $this->settle($delivery, [
                'status' => $attempt >= $maxAttempts ? WebhookService::STATUS_EXHAUSTED : WebhookService::STATUS_FAILED,
                'next_retry_at' => null,
            ]);

            return;
        }

        $delay = $webhooks->backoffFor($attempt);

        $this->settle($delivery, [
            'attempt' => $attempt + 1,
            'status' => WebhookService::STATUS_PENDING,
            'next_retry_at' => now()->addSeconds($delay),
        ]);

        DispatchWebhookDelivery::dispatch((int) $delivery->getKey())
            ->onQueue(WebhookService::QUEUE)
            ->delay(now()->addSeconds($delay))
            ->afterCommit();
    }

    /** @param array<string, mixed> $attributes */
    private function settle(WebhookDelivery $delivery, array $attributes): void
    {
        $delivery->forceFill($attributes)->save();
    }

    private function truncate(string $body): string
    {
        $body = trim($body);

        if (strlen($body) <= 1000) {
            return $body;
        }

        return substr($body, 0, 1000).'…';
    }
}
