<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Models\AbandonedCart;
use App\Models\Campaign;
use App\Services\AuditLogger;
use App\Support\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Campaign lifecycle and performance.
 *
 * Metrics are always recomputed from orders and campaign rows so a reported
 * number can never drift away from the underlying records.
 */
final class CampaignService
{
    public const PAGE_SIZE = 20;

    public const TYPES = [
        'promotion' => 'Promo Biasa',
        'flash_sale' => 'Flash Sale',
        'bundle' => 'Bundling',
        'referral' => 'Referral',
        'affiliate' => 'Affiliate',
        'cashback' => 'Cashback',
    ];

    public const STATUSES = [
        'draft' => 'Draf',
        'scheduled' => 'Terjadwal',
        'active' => 'Aktif',
        'paused' => 'Dijeda',
        'ended' => 'Selesai',
    ];

    public function __construct(private readonly CampaignRuleEngine $rules) {}

    /**
     * @return array<string, mixed>
     */
    public function index(int $page = 1, string $search = '', string $status = '', string $type = ''): array
    {
        $query = Campaign::query()->withCount(['products', 'categories']);

        if ($status !== '' && array_key_exists($status, self::STATUSES)) {
            $query->where('status', $status);
        }

        if ($type !== '' && array_key_exists($type, self::TYPES)) {
            $query->where('type', $type);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')->orWhere('slug', 'like', '%'.$search.'%');
            });
        }

        $page = max(1, $page);
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('created_at')
            ->forPage($page, self::PAGE_SIZE)
            ->get()
            ->map(fn (Campaign $campaign): array => $this->summarise($campaign))
            ->all();

        return [
            'rows' => $rows,
            'counts' => $this->counts(),
            'pagination' => [
                'total' => $total,
                'per_page' => self::PAGE_SIZE,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / self::PAGE_SIZE)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Campaign $campaign): array
    {
        $campaign->loadMissing(['products:id,name,sku,price,current_stock', 'categories:id,name']);

        $budget = (float) $campaign->budget;
        $revenue = (float) $campaign->revenue;
        $clicks = (int) $campaign->clicks;
        $conversions = (int) $campaign->conversions;
        $limit = $campaign->usage_limit;
        $used = (int) $campaign->used_count;

        return [
            'campaign' => $this->summarise($campaign),
            'timeline' => $this->timeline($campaign),
            'rules' => $this->rules->describe(is_array($campaign->rules) ? $campaign->rules : []),
            'performance' => [
                'budget' => $budget,
                'budget_formatted' => Currency::format($budget),
                'revenue' => $revenue,
                'revenue_formatted' => Currency::format($revenue),
                'roas' => $budget > 0 ? round(($revenue / $budget) * 100, 1) : 0.0,
                'clicks' => $clicks,
                'conversions' => $conversions,
                'ctr' => $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0.0,
                'cost_per_conversion' => $conversions > 0 ? $budget / $conversions : 0.0,
                'cost_per_conversion_formatted' => Currency::format($conversions > 0 ? $budget / $conversions : 0.0),
                'revenue_per_conversion' => $conversions > 0 ? $revenue / $conversions : 0.0,
                'revenue_per_conversion_formatted' => Currency::format($conversions > 0 ? $revenue / $conversions : 0.0),
            ],
            'quota' => [
                'limit' => $limit,
                'used' => $used,
                'remaining' => $limit === null ? null : max(0, (int) $limit - $used),
                'per_user_limit' => $campaign->per_user_limit,
                'usage_percent' => $limit === null ? null : ($limit > 0 ? round(($used / $limit) * 100, 1) : 0.0),
            ],
            'products' => $campaign->products->map(fn ($product): array => [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'sku' => (string) ($product->sku ?? ''),
                'price' => (float) $product->price,
                'price_formatted' => Currency::format((float) $product->price),
                'stock' => (int) $product->current_stock,
            ])->all(),
            'categories' => $campaign->categories->map(fn ($category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summarise(Campaign $campaign): array
    {
        $budget = (float) $campaign->budget;
        $revenue = (float) $campaign->revenue;

        return [
            'id' => (int) $campaign->id,
            'name' => (string) $campaign->name,
            'slug' => (string) $campaign->slug,
            'type' => (string) $campaign->type,
            'type_label' => self::TYPES[$campaign->type] ?? $campaign->type,
            'status' => (string) $campaign->status,
            'status_label' => self::STATUSES[$campaign->status] ?? $campaign->status,
            'live' => $this->rules->isLive($campaign),
            'budget' => $budget,
            'budget_formatted' => Currency::format($budget),
            'revenue' => $revenue,
            'revenue_formatted' => Currency::format($revenue),
            'roas' => $budget > 0 ? round(($revenue / $budget) * 100, 1) : 0.0,
            'clicks' => (int) $campaign->clicks,
            'conversions' => (int) $campaign->conversions,
            'used_count' => (int) $campaign->used_count,
            'usage_limit' => $campaign->usage_limit,
            'starts_at' => (string) ($campaign->starts_at?->format('Y-m-d H:i') ?? ''),
            'ends_at' => (string) ($campaign->ends_at?->format('Y-m-d H:i') ?? ''),
            'rule_count' => count(is_array($campaign->rules) ? $campaign->rules : []),
            'url' => route('admin.campaigns.show', $campaign->id),
        ];
    }

    /**
     * @return list<array{title: string, meta: string|null, body: string|null, state: string}>
     */
    private function timeline(Campaign $campaign): array
    {
        $now = now();
        $items = [
            [
                'title' => 'Kampanye dibuat',
                'meta' => (string) ($campaign->created_at?->format('d M Y H:i') ?? '-'),
                'body' => null,
                'state' => 'done',
            ],
        ];

        if ($campaign->starts_at !== null) {
            $items[] = [
                'title' => 'Kampanye dimulai',
                'meta' => (string) $campaign->starts_at->format('d M Y H:i'),
                'body' => null,
                'state' => $campaign->starts_at->greaterThan($now) ? 'pending' : 'done',
            ];
        }

        $items[] = [
            'title' => $campaign->status === 'active' ? 'Sedang berjalan' : 'Status: '.(self::STATUSES[$campaign->status] ?? $campaign->status),
            'meta' => null,
            'body' => $this->rules->isLive($campaign) ? 'Kampanye memenuhi seluruh syarat aktif.' : 'Kampanye belum memenuhi seluruh syarat aktif.',
            'state' => $campaign->status === 'active' ? 'current' : 'pending',
        ];

        if ($campaign->ends_at !== null) {
            $items[] = [
                'title' => 'Kampanye berakhir',
                'meta' => (string) $campaign->ends_at->format('d M Y H:i'),
                'body' => null,
                'state' => $campaign->ends_at->isPast() ? 'done' : 'pending',
            ];
        }

        return $items;
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = ['all' => 0];
        foreach (array_keys(self::STATUSES) as $status) {
            $counts[$status] = 0;
        }

        try {
            $counts['all'] = (int) Campaign::query()->count();
            foreach (Campaign::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->get() as $row) {
                $key = (string) $row->status;
                if (array_key_exists($key, $counts)) {
                    $counts[$key] = (int) $row->aggregate;
                }
            }
        } catch (\Throwable) {
            foreach ($counts as $key => $_) {
                $counts[$key] = 0;
            }
        }

        return $counts;
    }

    public function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'kampanye';
        $slug = $base;
        $suffix = 1;

        while (Campaign::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    public function toggle(Campaign $campaign, ?int $actorId): Campaign
    {
        $next = $campaign->status === 'active' ? 'paused' : 'active';

        $campaign->forceFill(array_filter([
            'status' => $next,
            'activated_at' => $next === 'active' ? now() : null,
            'activated_by' => $next === 'active' ? $actorId : null,
        ], fn ($value): bool => $value !== null))->save();

        app(AuditLogger::class)->log('campaign.toggled', $campaign, ['status' => $campaign->status], ['status' => $next], $actorId);

        return $campaign;
    }

    /**
     * Replace the product/category scope of a campaign.
     *
     * @param  list<int>  $productIds
     * @param  list<int>  $categoryIds
     */
    public function syncScope(Campaign $campaign, array $productIds, array $categoryIds): void
    {
        DB::transaction(function () use ($campaign, $productIds, $categoryIds): void {
            $campaign->products()->sync($productIds);
            $campaign->categories()->sync($categoryIds);
        });
    }
}
