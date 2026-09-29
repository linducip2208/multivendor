<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class WebhookStats
{
    private const LATENCY_SAMPLE_LIMIT = 5000;

    /** @return array<string, mixed> */
    public function summary(int $hours = 24, ?int $shopId = null, ?int $tenantId = null): array
    {
        $since = now()->subHours(max(1, $hours));

        $window = $this->scoped($this->window($since), $shopId, $tenantId);
        $lifetime = $this->scoped($this->window(null), $shopId, $tenantId);

        $totals = $window->clone()->count();
        $delivered = (clone $window)->where('status', WebhookService::STATUS_DELIVERED)->count();
        $failed = (clone $window)->where('status', WebhookService::STATUS_FAILED)->count();
        $exhausted = (clone $window)->where('status', WebhookService::STATUS_EXHAUSTED)->count();
        $pending = (clone $window)->where('status', WebhookService::STATUS_PENDING)->count();

        $settled = $delivered + $failed + $exhausted;

        return [
            'window_hours' => max(1, $hours),
            'since' => $since->toIso8601String(),
            'total' => $totals,
            'by_status' => [
                WebhookService::STATUS_DELIVERED => $delivered,
                WebhookService::STATUS_FAILED => $failed,
                WebhookService::STATUS_EXHAUSTED => $exhausted,
                WebhookService::STATUS_PENDING => $pending,
            ],
            'lifetime' => [
                'total' => (clone $lifetime)->count(),
                'by_status' => $this->byStatus($lifetime),
            ],
            'success_rate' => $settled > 0 ? round($delivered / $settled, 4) : 1.0,
            'failure_rate' => $settled > 0 ? round(($failed + $exhausted) / $settled, 4) : 0.0,
            'p95_latency_ms' => $this->p95Latency($since, $shopId, $tenantId),
            'p50_latency_ms' => $this->percentileLatency($since, 50, $shopId, $tenantId),
            'avg_latency_ms' => $this->averageLatency($since, $shopId, $tenantId),
            'endpoints' => [
                'total' => $this->endpointScope($shopId, $tenantId)->count(),
                'active' => $this->endpointScope($shopId, $tenantId)->where('is_active', true)->count(),
                'disabled' => $this->endpointScope($shopId, $tenantId)->where('is_active', false)->count(),
            ],
            'signature_scheme' => WebhookSigner::SCHEME,
        ];
    }

    /** @return array<string, int> */
    public function byStatus(?Carbon $since = null): array
    {
        $rows = $this->scoped($this->window($since))
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $counts = [
            WebhookService::STATUS_PENDING => 0,
            WebhookService::STATUS_DELIVERED => 0,
            WebhookService::STATUS_FAILED => 0,
            WebhookService::STATUS_EXHAUSTED => 0,
        ];

        foreach ($rows as $status => $aggregate) {
            $counts[(string) $status] = (int) $aggregate;
        }

        return $counts;
    }

    public function p95Latency(?Carbon $since = null, ?int $shopId = null, ?int $tenantId = null): ?int
    {
        return $this->percentileLatency($since, 95, $shopId, $tenantId);
    }

    public function percentileLatency(?Carbon $since = null, int $percentile = 95, ?int $shopId = null, ?int $tenantId = null): ?int
    {
        $samples = $this->scoped($this->window($since), $shopId, $tenantId)
            ->whereNotNull('duration_ms')
            ->orderByDesc('duration_ms')
            ->limit(self::LATENCY_SAMPLE_LIMIT)
            ->pluck('duration_ms')
            ->map(fn ($value): int => (int) $value)
            ->all();

        return $this->percentile($samples, $percentile);
    }

    public function averageLatency(?Carbon $since = null, ?int $shopId = null, ?int $tenantId = null): ?int
    {
        $average = $this->scoped($this->window($since), $shopId, $tenantId)
            ->whereNotNull('duration_ms')
            ->avg('duration_ms');

        return $average === null ? null : (int) round((float) $average);
    }

    public function failureRate(?int $endpointId = null, ?Carbon $since = null): float
    {
        $query = $this->scoped($this->window($since))->where('webhook_endpoint_id', $endpointId);

        $settled = (clone $query)->whereIn('status', [
            WebhookService::STATUS_DELIVERED,
            WebhookService::STATUS_FAILED,
            WebhookService::STATUS_EXHAUSTED,
        ])->count();

        if ($settled === 0) {
            return 0.0;
        }

        $failures = (clone $query)->whereIn('status', [
            WebhookService::STATUS_FAILED,
            WebhookService::STATUS_EXHAUSTED,
        ])->count();

        return round($failures / $settled, 4);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function perEndpoint(int $hours = 24): array
    {
        $since = now()->subHours(max(1, $hours));

        return WebhookEndpoint::query()
            ->orderBy('id')
            ->get()
            ->map(function (WebhookEndpoint $endpoint) use ($since): array {
                $delivered = WebhookDelivery::query()
                    ->where('webhook_endpoint_id', $endpoint->getKey())
                    ->where('status', WebhookService::STATUS_DELIVERED)
                    ->where('created_at', '>=', $since)
                    ->count();

                $failed = WebhookDelivery::query()
                    ->where('webhook_endpoint_id', $endpoint->getKey())
                    ->whereIn('status', [WebhookService::STATUS_FAILED, WebhookService::STATUS_EXHAUSTED])
                    ->where('created_at', '>=', $since)
                    ->count();

                $latency = WebhookDelivery::query()
                    ->where('webhook_endpoint_id', $endpoint->getKey())
                    ->whereNotNull('duration_ms')
                    ->where('created_at', '>=', $since)
                    ->orderByDesc('duration_ms')
                    ->limit(self::LATENCY_SAMPLE_LIMIT)
                    ->pluck('duration_ms')
                    ->map(fn ($value): int => (int) $value)
                    ->all();

                $settled = $delivered + $failed;

                return [
                    'endpoint_id' => (int) $endpoint->getKey(),
                    'name' => (string) $endpoint->name,
                    'url' => (string) $endpoint->url,
                    'is_active' => (bool) $endpoint->is_active,
                    'consecutive_failures' => (int) $endpoint->failure_count,
                    'last_triggered_at' => $endpoint->last_triggered_at?->toIso8601String(),
                    'delivered' => $delivered,
                    'failed' => $failed,
                    'failure_rate' => $settled > 0 ? round($failed / $settled, 4) : 0.0,
                    'p95_latency_ms' => $this->percentile($latency, 95),
                    'p50_latency_ms' => $this->percentile($latency, 50),
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentFailures(int $limit = 20): array
    {
        return WebhookDelivery::query()
            ->whereIn('status', [WebhookService::STATUS_FAILED, WebhookService::STATUS_EXHAUSTED])
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (WebhookDelivery $delivery): array => [
                'id' => (int) $delivery->getKey(),
                'endpoint_id' => (int) $delivery->webhook_endpoint_id,
                'event' => (string) $delivery->event,
                'event_id' => (string) $delivery->event_id,
                'status' => (string) $delivery->status,
                'attempt' => (int) $delivery->attempt,
                'response_status' => $delivery->response_status,
                'duration_ms' => $delivery->duration_ms,
                'response_body' => $delivery->response_body === null ? null : substr((string) $delivery->response_body, 0, 200),
                'created_at' => $delivery->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /** @param list<int> $sorted */
    private function percentile(array $sorted, int $percentile): ?int
    {
        $count = count($sorted);

        if ($count === 0) {
            return null;
        }

        $index = (int) ceil($percentile / 100 * $count) - 1;

        return $sorted[max(0, min($count - 1, $index))];
    }

    private function window(?Carbon $since): Builder
    {
        return WebhookDelivery::query()->when($since !== null, fn (Builder $query) => $query->where('created_at', '>=', $since));
    }

    private function scoped(Builder $query, ?int $shopId = null, ?int $tenantId = null): Builder
    {
        return $query->when(
            $shopId !== null || $tenantId !== null,
            fn (Builder $inner) => $inner->whereHas(
                'endpoint',
                fn (Builder $endpoint) => $endpoint
                    ->when($shopId !== null, fn (Builder $q) => $q->where('shop_id', $shopId))
                    ->when($tenantId !== null, fn (Builder $q) => $q->where('tenant_id', $tenantId))
            )
        );
    }

    private function endpointScope(?int $shopId = null, ?int $tenantId = null): Builder
    {
        return WebhookEndpoint::query()
            ->when($shopId !== null, fn (Builder $query) => $query->where('shop_id', $shopId))
            ->when($tenantId !== null, fn (Builder $query) => $query->where('tenant_id', $tenantId));
    }
}
