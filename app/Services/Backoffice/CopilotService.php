<?php

declare(strict_types=1);

namespace App\Services\Backoffice;

use App\Models\AiUsage;
use App\Models\Provider;
use App\Services\Ai\AdminPrompts;
use App\Services\Ai\AiService;
use App\Services\Analytics\DateRange;
use App\Services\Analytics\ExecutiveAnalyticsService;
use App\Services\Analytics\ProductAnalyticsService;
use App\Services\Analytics\StockAnalyticsService;
use App\Services\Analytics\VendorAnalyticsService;
use App\Support\Currency;
use Illuminate\Support\Facades\DB;

/**
 * The admin AI copilot.
 *
 * Two invariants hold for every task:
 *
 *  1. The model receives a *read-only* snapshot of aggregated facts. It is
 *     never handed a model instance, a query builder, or an id it can act on.
 *  2. Nothing the model returns is ever executed. The result is stored as
 *     advisory text and the caller decides what to do with it.
 */
final class CopilotService
{
    public function __construct(
        private readonly AiService $ai,
        private readonly ExecutiveAnalyticsService $executive,
        private readonly ProductAnalyticsService $products,
        private readonly StockAnalyticsService $stock,
        private readonly VendorAnalyticsService $vendors,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function providers(): array
    {
        return collect($this->ai->getActiveProviders())
            ->map(fn (Provider $provider): array => [
                'id' => (int) $provider->id,
                'name' => (string) $provider->name,
                'model' => is_array($provider->config) ? (string) ($provider->config['default_model'] ?? '') : '',
                'configured' => $provider->getApiKeyAttribute() !== null,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function console(): array
    {
        $range = DateRange::fromRequest(request(), 30);

        return [
            'tasks' => AdminPrompts::TASKS,
            'providers' => $this->providers(),
            'range' => $range->toArray(),
            'summary' => $this->executive->summary($range),
            'recent' => $this->recentRuns(10),
            'disclaimer' => 'Output AI bersifat consultatif. Teks yang dihasilkan tidak pernah menjalankan aksi keuangan atau mengubah data.',
        ];
    }

    /**
     * Build the fact snapshot handed to the model for a task.
     *
     * @return array<string, mixed>
     */
    public function facts(string $task, DateRange $range): array
    {
        return match ($task) {
            'sales_analysis' => $this->salesFacts($range),
            'revenue_anomaly' => $this->anomalyFacts($range),
            'product_performance' => $this->productFacts($range),
            'vendor_performance' => $this->vendorFacts($range),
            'campaign_suggestions' => $this->campaignFacts($range),
            'inventory_risk' => $this->inventoryFacts($range),
            'segment_suggestions' => $this->segmentFacts($range),
            'report_summary' => $this->summaryFacts($range),
            default => [],
        };
    }

    /**
     * Run a task and record the token usage.
     *
     * @return array{success: bool, content: string, model: string, tokens: array<string, int>, duration_ms: int, error: string|null}
     */
    public function run(Provider $provider, string $task, DateRange $range, ?string $model, ?string $note = null, ?int $actorId = null): array
    {
        $facts = $this->facts($task, $range);
        $prompt = $this->buildPrompt($task, $facts, $range, $note);

        $started = microtime(true);
        $result = $this->ai->chat(
            $provider,
            $prompt,
            AdminPrompts::systemPrompt($task),
            $model,
            ['temperature' => 0.3, 'max_tokens' => 2000],
        );

        $duration = (int) round((microtime(true) - $started) * 1000);
        $tokens = is_array($result['tokens'] ?? null) ? $result['tokens'] : [];
        $success = (bool) ($result['success'] ?? false);

        $this->logUsage($provider, $task, $tokens, $duration, $success, $success ? null : (string) ($result['error'] ?? 'Tidak diketahui'), $actorId);

        return [
            'success' => $success,
            'content' => (string) ($result['content'] ?? ''),
            'model' => (string) ($result['model'] ?? ($model ?? 'default')),
            'tokens' => [
                'prompt' => (int) ($tokens['prompt_tokens'] ?? 0),
                'completion' => (int) ($tokens['completion_tokens'] ?? 0),
                'total' => (int) ($tokens['total_tokens'] ?? 0),
            ],
            'duration_ms' => $duration,
            'error' => $success ? null : $this->safeError((string) ($result['error'] ?? '')),
        ];
    }

    private function safeError(string $error): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $error) ?? '');

        if ($message === '') {
            return 'Provider AI tidak dapat dihubungi.';
        }

        return \Illuminate\Support\Str::limit($message, 180);
    }

    private function buildPrompt(string $task, array $facts, DateRange $range, ?string $note): string
    {
        $json = (string) json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $lines = [
            'Tugas: '.AdminPrompts::taskLabel($task).'.',
            'Periode laporan: '.$range->from->toDateString().' sampai '.$range->to->toDateString().' ('.$range->days().' hari).',
            '',
            'Berikut data agregat yang terukur dari katalog dan transaksi:',
            '```json',
            $json,
            '```',
        ];

        if ($note !== null && trim($note) !== '') {
            $lines[] = '';
            $lines[] = 'Catatan operator yang perlu dijawab:';
            $lines[] = trim($note);
        }

        $lines[] = '';
        $lines[] = 'Tulis analisis dalam Bahasa Indonesia. Sebutkan hanya angka yang ada di atas. Tulis "data tidak tersedia" untuk hal yang tidak dapat dijawab.';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, int|string|null>  $tokens
     */
    private function logUsage(Provider $provider, string $task, array $tokens, int $duration, bool $success, ?string $error, ?int $actorId): void
    {
        try {
            AiUsage::create([
                'user_id' => $actorId,
                'provider_id' => $provider->id,
                'feature' => $task,
                'model' => is_array($provider->config) ? (string) ($provider->config['default_model'] ?? '') : null,
                'prompt_tokens' => (int) ($tokens['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($tokens['completion_tokens'] ?? 0),
                'estimated_cost' => $this->estimateCost((int) ($tokens['prompt_tokens'] ?? 0), (int) ($tokens['completion_tokens'] ?? 0)),
                'duration_ms' => $duration,
                'success' => $success,
                'error' => $error,
            ]);
        } catch (\Throwable) {
        }
    }

    private function estimateCost(int $promptTokens, int $completionTokens): float
    {
        $promptRate = (float) \App\Models\SystemSetting::get('ai_cost_per_1k_prompt', '0');
        $completionRate = (float) \App\Models\SystemSetting::get('ai_cost_per_1k_completion', '0');

        return round((($promptTokens / 1000) * $promptRate) + (($completionTokens / 1000) * $completionRate), 6);
    }

    /**
     * @return array<string, mixed>
     */
    public function usageReport(int $page = 1, int $perPage = 25, string $feature = ''): array
    {
        $query = AiUsage::query()->with(['user:id,name', 'provider:id,name']);

        if ($feature !== '') {
            $query->where('feature', $feature);
        }

        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('id')->forPage($page, $perPage)->get()
            ->map(fn (AiUsage $usage): array => [
                'id' => (int) $usage->id,
                'feature' => (string) $usage->feature,
                'feature_label' => AdminPrompts::taskLabel((string) $usage->feature),
                'model' => (string) ($usage->model ?? '-'),
                'provider' => (string) ($usage->provider?->name ?? '-'),
                'user' => (string) ($usage->user?->name ?? 'Sistem'),
                'prompt_tokens' => (int) $usage->prompt_tokens,
                'completion_tokens' => (int) $usage->completion_tokens,
                'total_tokens' => (int) $usage->totalTokens(),
                'cost' => (float) $usage->estimated_cost,
                'duration_ms' => (int) $usage->duration_ms,
                'success' => (bool) $usage->success,
                'error' => \Illuminate\Support\Str::limit((string) ($usage->error ?? ''), 120),
                'at' => (string) ($usage->created_at?->format('Y-m-d H:i') ?? ''),
            ])
            ->all();

        $totals = AiUsage::query()
            ->selectRaw('COALESCE(SUM(prompt_tokens), 0) as prompt_tokens, COALESCE(SUM(completion_tokens), 0) as completion_tokens, COALESCE(SUM(estimated_cost), 0) as cost, COUNT(*) as calls, SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successes')
            ->first();

        $byFeature = AiUsage::query()
            ->select('feature')
            ->selectRaw('COUNT(*) as calls, COALESCE(SUM(prompt_tokens + completion_tokens), 0) as tokens, COALESCE(SUM(estimated_cost), 0) as cost')
            ->groupBy('feature')
            ->orderByDesc('calls')
            ->limit(10)
            ->get()
            ->map(fn (AiUsage $row): array => [
                'feature' => (string) $row->feature,
                'label' => AdminPrompts::taskLabel((string) $row->feature),
                'calls' => (int) $row->calls,
                'tokens' => (int) $row->tokens,
                'cost' => (float) $row->cost,
            ])
            ->all();

        $calls = (int) ($totals->calls ?? 0);
        $successes = (int) ($totals->successes ?? 0);

        return [
            'rows' => $rows,
            'by_feature' => $byFeature,
            'totals' => [
                'calls' => $calls,
                'prompt_tokens' => (int) ($totals->prompt_tokens ?? 0),
                'completion_tokens' => (int) ($totals->completion_tokens ?? 0),
                'tokens' => (int) ($totals->prompt_tokens ?? 0) + (int) ($totals->completion_tokens ?? 0),
                'cost' => (float) ($totals->cost ?? 0),
                'cost_formatted' => Currency::number((float) ($totals->cost ?? 0), 4),
                'success_rate' => $calls > 0 ? round(($successes / $calls) * 100, 1) : 0.0,
            ],
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentRuns(int $limit): array
    {
        try {
            return AiUsage::query()->with('user:id,name')->orderByDesc('id')->limit($limit)->get()
                ->map(fn (AiUsage $usage): array => [
                    'feature' => (string) $usage->feature,
                    'label' => AdminPrompts::taskLabel((string) $usage->feature),
                    'user' => (string) ($usage->user?->name ?? 'Sistem'),
                    'tokens' => (int) $usage->totalTokens(),
                    'success' => (bool) $usage->success,
                    'at' => (string) ($usage->created_at?->diffForHumans() ?? ''),
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function salesFacts(DateRange $range): array
    {
        $summary = $this->executive->summary($range);

        $byHour = DB::table('orders')
            ->whereIn('order_status', ['confirmed', 'processing', 'packed', 'shipped', 'delivered', 'completed'])
            ->whereIn('payment_status', ['paid', 'partial', 'refunded'])
            ->whereBetween('created_at', [$range->from, $range->to])
            ->selectRaw('HOUR(created_at) as bucket, COUNT(*) as orders, SUM(total) as amount')
            ->groupBy('bucket')
            ->orderByDesc('orders')
            ->limit(6)
            ->get()
            ->map(fn ($row): array => [
                'hour' => (int) $row->bucket,
                'orders' => (int) $row->orders,
                'amount' => (float) $row->amount,
            ])
            ->all();

        return [
            'gmv' => (float) ($summary['gmv']['value'] ?? 0),
            'orders' => (int) ($summary['orders']['value'] ?? 0),
            'aov' => (float) ($summary['aov']['value'] ?? 0),
            'cancelled_orders' => (int) ($summary['cancelled']['value'] ?? 0),
            'daily' => $summary['series'] ?? [],
            'busiest_hours' => $byHour,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function anomalyFacts(DateRange $range): array
    {
        $series = $this->executive->dailySeries($range);
        $values = $series['gmv'];
        $labels = $series['labels'];

        if ($values === []) {
            return ['anomalies' => [], 'note' => 'Tidak ada transaksi pada periode ini.'];
        }

        $average = array_sum($values) / count($values);
        $anomalies = [];

        foreach ($values as $index => $value) {
            $deviation = $average > 0 ? (($value - $average) / $average) * 100 : 0.0;

            if (abs($deviation) >= 40.0) {
                $anomalies[] = [
                    'date' => $labels[$index] ?? '',
                    'amount' => $value,
                    'orders' => $series['orders'][$index] ?? 0,
                    'deviation_percent' => round($deviation, 1),
                ];
            }
        }

        return [
            'average_daily' => round($average, 2),
            'days_measured' => count($values),
            'anomalies' => $anomalies,
            'thresholds' => ['spike' => 40, 'drop' => -40],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productFacts(DateRange $range): array
    {
        $report = $this->products->report($range, 10, 1);

        return [
            'kpis' => $report['kpis'] ?? [],
            'top_revenue' => array_slice($report['rows'] ?? [], 0, 10),
            'categories' => $report['categories'] ?? [],
            'brands' => $report['brands'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function vendorFacts(DateRange $range): array
    {
        $report = $this->vendors->report($range, 10, 1);

        return [
            'kpis' => $report['kpis'] ?? [],
            'rows' => array_slice($report['rows'] ?? [], 0, 10),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignFacts(DateRange $range): array
    {
        try {
            return [
                'active_campaigns' => (int) \App\Models\Campaign::query()->active()->count(),
                'recent_campaigns' => \App\Models\Campaign::query()
                    ->orderByDesc('id')
                    ->limit(8)
                    ->get(['name', 'type', 'status', 'budget', 'revenue', 'clicks', 'conversions', 'starts_at', 'ends_at'])
                    ->map(fn (\App\Models\Campaign $campaign): array => [
                        'name' => (string) $campaign->name,
                        'type' => (string) $campaign->type,
                        'status' => (string) $campaign->status,
                        'budget' => (float) $campaign->budget,
                        'revenue' => (float) $campaign->revenue,
                        'clicks' => (int) $campaign->clicks,
                        'conversions' => (int) $campaign->conversions,
                        'starts_at' => (string) ($campaign->starts_at?->format('Y-m-d') ?? ''),
                        'ends_at' => (string) ($campaign->ends_at?->format('Y-m-d') ?? ''),
                    ])
                    ->all(),
                'best_categories' => $this->products->report($range, 5, 1)['categories'] ?? [],
            ];
        } catch (\Throwable) {
            return ['note' => 'Data kampanye belum tersedia.'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function inventoryFacts(DateRange $range): array
    {
        $report = $this->stock->report($range, 10, 1, '', 'low');

        return [
            'kpis' => $report['kpis'] ?? [],
            'low_stock' => array_slice($report['rows'] ?? [], 0, 10),
            'by_warehouse' => $report['by_warehouse'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function segmentFacts(DateRange $range): array
    {
        try {
            return [
                'segments' => DB::table('customer_segments')
                    ->orderByDesc('member_count')
                    ->limit(8)
                    ->get(['name', 'type', 'member_count', 'description'])
                    ->map(fn ($row): array => [
                        'name' => (string) $row->name,
                        'type' => (string) $row->type,
                        'members' => (int) $row->member_count,
                        'description' => (string) ($row->description ?? ''),
                    ])
                    ->all(),
                'repeat_buyers' => (int) DB::table('orders')
                    ->whereIn('payment_status', ['paid', 'partial', 'refunded'])
                    ->whereNotIn('order_status', ['canceled', 'failed'])
                    ->whereBetween('created_at', [$range->from, $range->to])
                    ->selectRaw('customer_id')
                    ->groupBy('customer_id')
                    ->havingRaw('COUNT(*) >= 2')
                    ->get()
                    ->count(),
                'coupon_users' => (int) DB::table('orders')
                    ->whereNotNull('coupon_code')
                    ->whereBetween('created_at', [$range->from, $range->to])
                    ->distinct()
                    ->count('customer_id'),
            ];
        } catch (\Throwable) {
            return ['note' => 'Data segmen belum tersedia.'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryFacts(DateRange $range): array
    {
        $summary = $this->executive->summary($range);

        $kpis = [];
        foreach ($summary as $key => $tile) {
            if (is_array($tile) && array_key_exists('value', $tile)) {
                $kpis[(string) ($tile['label'] ?? $key)] = $tile['value'];
            }
        }

        return [
            'period' => $range->toArray(),
            'kpis' => $kpis,
            'trend' => $summary['gmv']['trend'] ?? null,
        ];
    }
}
