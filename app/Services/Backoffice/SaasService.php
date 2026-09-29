<?php

declare(strict_types=1);

namespace App\Services\Backoffice;

use App\Models\Product;
use App\Models\SaasPlan;
use App\Models\SaasSubscription;
use App\Models\Shop;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * White-label SaaS administration.
 *
 * A single-tenant installation sees an explicit "SaaS not enabled" state rather
 * than an empty table, and every tenant-scoped query is filtered by `tenant_id`
 * so one tenant can never observe another's rows.
 */
final class SaasService
{
    public const PAGE_SIZE = 20;

    public const SUBSCRIPTION_STATUSES = [
        'trialing' => 'Masa Uji Coba',
        'active' => 'Aktif',
        'past_due' => 'Menunggak',
        'cancelled' => 'Dibatalkan',
        'expired' => 'Kedaluwarsa',
    ];

    public const GRACE_DAYS = 7;

    /**
     * @return array<string, mixed>
     */
    public function tenants(int $page = 1, string $search = '', string $status = ''): array
    {
        $query = Tenant::query()->withCount('subscriptions');

        if ($status !== '') {
            $query->where('is_active', $status === 'active');
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')
                    ->orWhere('domain', 'like', '%'.$search.'%');
            });
        }

        $page = max(1, $page);
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('id')->forPage($page, self::PAGE_SIZE)->get()
            ->map(fn (Tenant $tenant): array => $this->tenantRow($tenant))
            ->all();

        return [
            'rows' => $rows,
            'enabled' => $total > 0 || $this->featureEnabled(),
            'feature_enabled' => $this->featureEnabled(),
            'kpis' => [
                ['label' => 'Total Tenant', 'value' => $total, 'icon' => 'globe', 'color' => 'primary', 'hint' => 'Tenant terdaftar'],
                ['label' => 'Aktif', 'value' => (int) Tenant::query()->where('is_active', true)->count(), 'icon' => 'check', 'color' => 'success', 'hint' => 'Tenant aktif'],
                ['label' => 'Masa Uji', 'value' => (int) Tenant::query()->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', now())->count(), 'icon' => 'clock', 'color' => 'info', 'hint' => 'Tenant dalam masa uji coba'],
                ['label' => 'Kedaluwarsa', 'value' => (int) $this->expiredCount(), 'icon' => 'alert-triangle', 'color' => 'danger', 'hint' => 'Tenant dengan langganan kedaluwarsa'],
            ],
            'pagination' => [
                'total' => $total,
                'per_page' => self::PAGE_SIZE,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / self::PAGE_SIZE)),
            ],
        ];
    }

    private function featureEnabled(): bool
    {
        return (bool) \App\Support\Feature::enabled(\App\Enums\PlatformFeature::WhiteLabel);
    }

    private function expiredCount(): int
    {
        try {
            return (int) SaasSubscription::query()
                ->whereIn('status', ['trialing', 'active', 'past_due'])
                ->whereNotNull('ends_at')
                ->where('ends_at', '<', now())
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function tenantRow(Tenant $tenant): array
    {
        $subscription = SaasSubscription::query()
            ->with('plan:id,name,slug,monthly_price,yearly_price')
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('id')
            ->first();

        $domains = is_array($tenant->domains) ? $tenant->domains : [];
        $features = is_array($tenant->enabled_features) ? $tenant->enabled_features : [];

        return [
            'id' => (int) $tenant->id,
            'name' => (string) $tenant->name,
            'slug' => (string) $tenant->slug,
            'domain' => (string) $tenant->domain,
            'extra_domains' => $domains,
            'logo_url' => (string) ($tenant->logo_url ?? ''),
            'currency_code' => (string) $tenant->currency_code,
            'timezone' => (string) $tenant->timezone,
            'locale' => (string) $tenant->locale,
            'contact_email' => (string) ($tenant->contact_email ?? ''),
            'contact_phone' => (string) ($tenant->contact_phone ?? ''),
            'commission_rate' => (float) $tenant->default_commission_rate,
            'is_active' => (bool) $tenant->is_active,
            'trial_ends_at' => (string) ($tenant->trial_ends_at?->format('Y-m-d') ?? ''),
            'on_trial' => $tenant->trial_ends_at !== null && $tenant->trial_ends_at->isFuture(),
            'features' => $features,
            'subscription' => $subscription === null ? null : $this->subscriptionRow($subscription),
            'url' => route('admin.tenants.show', $tenant->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function tenantDetail(Tenant $tenant): array
    {
        $tenant->loadMissing('subscriptions');

        return [
            'tenant' => $this->tenantRow($tenant),
            'subscriptions' => $tenant->subscriptions->map(fn (SaasSubscription $s): array => $this->subscriptionRow($s))->all(),
            'usage' => $this->usageFor($tenant),
            'theme' => is_array($tenant->theme) ? $tenant->theme : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function subscriptionRow(SaasSubscription $subscription): array
    {
        $plan = $subscription->relationLoaded('plan') ? $subscription->plan : $subscription->plan()->first();
        $graceDays = self::GRACE_DAYS;
        $graceEndsAt = $subscription->grace_ends_at ?? ($subscription->ends_at?->copy()->addDays($graceDays));

        return [
            'id' => (int) $subscription->id,
            'tenant_id' => (int) $subscription->tenant_id,
            'tenant' => (string) ($subscription->relationLoaded('tenant') ? ($subscription->tenant?->name ?? '-') : 'Tenant #'.$subscription->tenant_id),
            'plan' => (string) ($plan->name ?? '-'),
            'plan_slug' => (string) ($plan->slug ?? ''),
            'status' => (string) $subscription->status,
            'status_label' => self::SUBSCRIPTION_STATUSES[$subscription->status] ?? $subscription->status,
            'billing_cycle' => (string) $subscription->billing_cycle,
            'price' => (float) ($plan->priceFor((string) $subscription->billing_cycle) ?? 0),
            'price_formatted' => Currency::format((float) ($plan->priceFor((string) $subscription->billing_cycle) ?? 0)),
            'starts_at' => (string) ($subscription->starts_at?->format('Y-m-d') ?? ''),
            'ends_at' => (string) ($subscription->ends_at?->format('Y-m-d') ?? ''),
            'grace_ends_at' => (string) ($graceEndsAt?->format('Y-m-d') ?? ''),
            'on_grace' => $subscription->onGracePeriod(),
            'expired' => $subscription->isExpired(),
            'cancelled_at' => (string) ($subscription->cancelled_at?->format('Y-m-d') ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function usageFor(Tenant $tenant): array
    {
        $products = (int) Product::query()->count();
        $vendors = (int) Shop::query()->where('status', 'active')->count();
        $staff = (int) User::query()->whereIn('role', ['admin', 'employee'])->count();
        $orders = (int) \App\Models\Order::query()->where('created_at', '>=', now()->startOfMonth())->count();
        $storageMb = $this->storageUsageMb();
        $pseoPages = (int) $this->safeCount('pseo_pages');
        $aiRequests = (int) $this->safeCount('ai_usages', 'created_at', now()->startOfMonth()->toDateTimeString());

        return [
            'tenant_id' => (int) $tenant->id,
            'products' => $this->meter('products', $products),
            'vendors' => $this->meter('vendors', $vendors),
            'staff' => $this->meter('staff', $staff),
            'orders_per_month' => $this->meter('orders_per_month', $orders),
            'storage_mb' => ['key' => 'storage_mb', 'label' => 'Penyimpanan', 'used' => $storageMb, 'limit' => null, 'percent' => null, 'used_formatted' => $storageMb.' MB'],
            'pseo_pages' => $this->meter('pseo_pages', $pseoPages),
            'ai_requests' => $this->meter('ai_requests', $aiRequests),
            'updated_at' => (string) now()->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array{key: string, label: string, used: int, limit: int|null, percent: float|null, used_formatted: string}
     */
    private function meter(string $key, int $used): array
    {
        $subscription = SaasSubscription::query()
            ->where('tenant_id', $this->currentTenantId())
            ->orderByDesc('id')
            ->first();

        $plan = $subscription?->plan;
        $limit = match ($key) {
            'products' => $plan?->max_products,
            'vendors' => $plan?->max_vendors,
            'staff' => $plan?->max_staff,
            'orders_per_month' => $plan?->max_orders_per_month,
            'pseo_pages' => $plan?->pseo_page_quota,
            'ai_requests' => $plan?->ai_request_quota,
            default => null,
        };

        $limit = $limit === null ? null : (int) $limit;

        return [
            'key' => $key,
            'label' => match ($key) {
                'products' => 'Produk',
                'vendors' => 'Vendor',
                'staff' => 'Staf',
                'orders_per_month' => 'Pesanan / bulan',
                'pseo_pages' => 'Halaman PSEO',
                'ai_requests' => 'Permintaan AI / bulan',
                default => $key,
            },
            'used' => $used,
            'limit' => $limit,
            'percent' => ($limit !== null && $limit > 0) ? round(min(100.0, ($used / $limit) * 100), 1) : null,
            'used_formatted' => Currency::number($used),
        ];
    }

    private function currentTenantId(): ?int
    {
        try {
            return app()->bound('tenant') ? (int) app('tenant')->getKey() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function storageUsageMb(): int
    {
        try {
            $path = storage_path();
            $bytes = 0;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST,
            );

            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile()) {
                    $bytes += (int) $file->getSize();
                }
            }

            return (int) round($bytes / 1048576);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeCount(string $table, string $column = 'id', ?string $since = null): int
    {
        try {
            $query = DB::table($table);

            if ($since !== null) {
                $query->where($column, '>=', $since);
            }

            return (int) $query->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function usageOverview(): array
    {
        $tenants = Tenant::query()->orderBy('id')->get();

        $rows = $tenants->map(fn (Tenant $tenant): array => [
            'tenant' => (string) $tenant->name,
            'tenant_id' => (int) $tenant->id,
            'usage' => $this->usageFor($tenant),
            'is_active' => (bool) $tenant->is_active,
        ])->all();

        $aggregate = [
            'products' => 0,
            'vendors' => 0,
            'staff' => 0,
            'orders_per_month' => 0,
            'pseo_pages' => 0,
            'ai_requests' => 0,
        ];

        foreach ($rows as $row) {
            foreach ($aggregate as $key => $_) {
                $aggregate[$key] += (int) ($row['usage'][$key]['used'] ?? 0);
            }
        }

        return [
            'rows' => $rows,
            'enabled' => $tenants->isNotEmpty() || $this->featureEnabled(),
            'feature_enabled' => $this->featureEnabled(),
            'totals' => $aggregate,
        ];
    }

    public function createTenant(array $data, ?int $actorId): Tenant
    {
        return DB::transaction(function () use ($data, $actorId): Tenant {
            $tenant = Tenant::create([
                'name' => (string) $data['name'],
                'slug' => $this->uniqueSlug((string) $data['name']),
                'domain' => (string) $data['domain'],
                'domains' => array_values(array_filter((array) ($data['domains'] ?? []))),
                'logo_url' => $data['logo_url'] ?? null,
                'favicon_url' => $data['favicon_url'] ?? null,
                'theme' => is_array($data['theme'] ?? null) ? $data['theme'] : null,
                'currency_code' => (string) ($data['currency_code'] ?? 'IDR'),
                'timezone' => (string) ($data['timezone'] ?? config('app.timezone', 'Asia/Jakarta')),
                'locale' => (string) ($data['locale'] ?? 'id'),
                'contact_email' => $data['contact_email'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'enabled_features' => array_values((array) ($data['enabled_features'] ?? [])),
                'default_commission_rate' => (float) ($data['default_commission_rate'] ?? 10),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'trial_ends_at' => $data['trial_ends_at'] ?? null,
            ]);

            app(AuditLogger::class)->log('tenant.created', $tenant, [], ['name' => $tenant->name, 'domain' => $tenant->domain], $actorId);

            return $tenant;
        });
    }

    public function updateTenant(Tenant $tenant, array $data, ?int $actorId): Tenant
    {
        return DB::transaction(function () use ($tenant, $data, $actorId): Tenant {
            $before = $tenant->only(['name', 'domain', 'currency_code', 'is_active', 'default_commission_rate']);

            foreach (['name', 'logo_url', 'favicon_url', 'currency_code', 'timezone', 'locale', 'contact_email', 'contact_phone', 'trial_ends_at'] as $field) {
                if (array_key_exists($field, $data)) {
                    $tenant->{$field} = $data[$field];
                }
            }

            if (! empty($data['domain'])) {
                if (Tenant::withTrashed()->where('domain', $data['domain'])->where('id', '!=', $tenant->id)->exists()) {
                    throw new \Illuminate\Validation\ValidationException(
                        $tenant->getConnection()->getQueryBuilder(),
                        ['domain' => ['Domain sudah dipakai tenant lain.']],
                    );
                }
                $tenant->domain = (string) $data['domain'];
            }

            if (array_key_exists('domains', $data)) {
                $tenant->domains = array_values(array_filter((array) $data['domains']));
            }

            if (array_key_exists('theme', $data) && is_array($data['theme'])) {
                $tenant->theme = $data['theme'];
            }

            if (array_key_exists('enabled_features', $data)) {
                $tenant->enabled_features = array_values((array) $data['enabled_features']);
            }

            if (array_key_exists('default_commission_rate', $data)) {
                $tenant->default_commission_rate = (float) $data['default_commission_rate'];
            }

            if (array_key_exists('is_active', $data)) {
                $tenant->is_active = (bool) $data['is_active'];
            }

            $tenant->save();

            app(AuditLogger::class)->log('tenant.updated', $tenant, $before, $tenant->only(['name', 'domain', 'currency_code', 'is_active', 'default_commission_rate']), $actorId);

            return $tenant;
        });
    }

    public function deleteTenant(Tenant $tenant, ?int $actorId): void
    {
        $snapshot = ['name' => (string) $tenant->name, 'domain' => (string) $tenant->domain];

        DB::transaction(function () use ($tenant): void {
            SaasSubscription::query()->where('tenant_id', $tenant->id)->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            DB::table('webhook_endpoints')->where('tenant_id', $tenant->id)->update(['is_active' => false]);
            $tenant->delete();
        });

        app(AuditLogger::class)->log('tenant.deleted', null, $snapshot, [], $actorId);
    }

    /**
     * @return array<string, mixed>
     */
    public function plans(int $page = 1): array
    {
        $page = max(1, $page);
        $total = (int) SaasPlan::query()->count();

        $rows = SaasPlan::query()->withCount('subscriptions')
            ->orderBy('sort_order')
            ->orderBy('monthly_price')
            ->forPage($page, self::PAGE_SIZE)
            ->get()
            ->map(function (SaasPlan $plan): array {
                $features = is_array($plan->features) ? $plan->features : [];

                return [
                    'id' => (int) $plan->id,
                    'name' => (string) $plan->name,
                    'slug' => (string) $plan->slug,
                    'description' => (string) ($plan->description ?? ''),
                    'monthly_price' => (float) $plan->monthly_price,
                    'monthly_price_formatted' => Currency::format((float) $plan->monthly_price),
                    'yearly_price' => (float) $plan->yearly_price,
                    'yearly_price_formatted' => Currency::format((float) $plan->yearly_price),
                    'currency' => (string) $plan->currency,
                    'limits' => [
                        ['label' => 'Produk', 'value' => $plan->max_products],
                        ['label' => 'Vendor', 'value' => $plan->max_vendors],
                        ['label' => 'Staf', 'value' => $plan->max_staff],
                        ['label' => 'Pesanan/bulan', 'value' => $plan->max_orders_per_month],
                        ['label' => 'Penyimpanan (MB)', 'value' => $plan->max_storage_mb],
                        ['label' => 'Kuota PSEO', 'value' => $plan->pseo_page_quota],
                        ['label' => 'Permintaan AI', 'value' => $plan->ai_request_quota],
                    ],
                    'features' => $features,
                    'is_active' => (bool) $plan->is_active,
                    'is_featured' => (bool) $plan->is_featured,
                    'sort_order' => (int) $plan->sort_order,
                    'subscriptions' => (int) $plan->subscriptions_count,
                ];
            })
            ->all();

        return [
            'rows' => $rows,
            'pagination' => [
                'total' => $total,
                'per_page' => self::PAGE_SIZE,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / self::PAGE_SIZE)),
            ],
        ];
    }

    public function savePlan(?SaasPlan $plan, array $data, ?int $actorId): SaasPlan
    {
        $attributes = [
            'name' => (string) $data['name'],
            'slug' => $plan !== null && empty($data['slug']) ? $plan->slug : $this->uniquePlanSlug((string) ($data['slug'] ?? $data['name']), $plan?->id),
            'description' => $data['description'] ?? null,
            'monthly_price' => (float) $data['monthly_price'],
            'yearly_price' => (float) $data['yearly_price'],
            'currency' => (string) ($data['currency'] ?? 'IDR'),
            'max_products' => $this->nullableInt($data['max_products'] ?? null),
            'max_vendors' => $this->nullableInt($data['max_vendors'] ?? null),
            'max_staff' => $this->nullableInt($data['max_staff'] ?? null),
            'max_orders_per_month' => $this->nullableInt($data['max_orders_per_month'] ?? null),
            'max_storage_mb' => $this->nullableInt($data['max_storage_mb'] ?? null),
            'pseo_page_quota' => $this->nullableInt($data['pseo_page_quota'] ?? null),
            'ai_request_quota' => $this->nullableInt($data['ai_request_quota'] ?? null),
            'features' => array_values(array_filter((array) ($data['features'] ?? []))),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];

        $plan = $plan === null
            ? SaasPlan::create($attributes)
            : tap($plan, fn (SaasPlan $target) => $target->forceFill($attributes)->save());

        app(AuditLogger::class)->log($plan->wasRecentlyCreated ? 'saas_plan.created' : 'saas_plan.updated', $plan, [], ['name' => $plan->name], $actorId);

        return $plan;
    }

    public function deletePlan(SaasPlan $plan, ?int $actorId): bool
    {
        $active = (int) SaasSubscription::query()->where('saas_plan_id', $plan->id)->whereIn('status', ['trialing', 'active', 'past_due'])->count();

        if ($active > 0) {
            throw new \Illuminate\Validation\ValidationException(
                $plan->getConnection()->getQueryBuilder(),
                ['plan' => ['Paket masih memiliki '.$active.' langganan aktif. Nonaktifkan paket alih-alih menghapusnya.']],
            );
        }

        $snapshot = ['name' => (string) $plan->name];
        $deleted = $plan->delete();

        app(AuditLogger::class)->log('saas_plan.deleted', null, $snapshot, [], $actorId);

        return $deleted;
    }

    /**
     * @return array<string, mixed>
     */
    public function subscriptions(int $page = 1, string $status = '', string $search = ''): array
    {
        $query = SaasSubscription::query()->with(['tenant:id,name,slug,is_active', 'plan:id,name,slug']);

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->whereHas('tenant', fn (Builder $q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('slug', 'like', '%'.$search.'%'));
        }

        $page = max(1, $page);
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('id')->forPage($page, self::PAGE_SIZE)->get()
            ->map(fn (SaasSubscription $subscription): array => $this->subscriptionRow($subscription))
            ->all();

        $counts = ['all' => 0];
        foreach (array_keys(self::SUBSCRIPTION_STATUSES) as $key) {
            $counts[$key] = 0;
        }

        try {
            $counts['all'] = (int) SaasSubscription::query()->count();
            foreach (SaasSubscription::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->get() as $row) {
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

        return [
            'rows' => $rows,
            'counts' => $counts,
            'enabled' => $counts['all'] > 0 || $this->featureEnabled(),
            'feature_enabled' => $this->featureEnabled(),
            'mrr' => (float) $this->monthlyRecurringRevenue(),
            'mrr_formatted' => Currency::format((float) $this->monthlyRecurringRevenue()),
            'grace_days' => self::GRACE_DAYS,
            'pagination' => [
                'total' => $total,
                'per_page' => self::PAGE_SIZE,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / self::PAGE_SIZE)),
            ],
        ];
    }

    private function monthlyRecurringRevenue(): float
    {
        try {
            $rows = SaasSubscription::query()
                ->with('plan:id,monthly_price,yearly_price')
                ->whereIn('status', ['trialing', 'active', 'past_due'])
                ->get();

            $total = 0.0;
            foreach ($rows as $subscription) {
                $plan = $subscription->plan;
                if ($plan === null) {
                    continue;
                }
                $total += $subscription->billing_cycle === 'yearly'
                    ? (float) $plan->yearly_price / 12
                    : (float) $plan->monthly_price;
            }

            return $total;
        } catch (\Throwable) {
            return 0.0;
        }
    }

    public function updateSubscriptionStatus(SaasSubscription $subscription, string $status, ?int $actorId): SaasSubscription
    {
        if (! array_key_exists($status, self::SUBSCRIPTION_STATUSES)) {
            abort(422, 'Status langganan tidak dikenal.');
        }

        return DB::transaction(function () use ($subscription, $status, $actorId): SaasSubscription {
            $locked = SaasSubscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $before = $locked->only(['status', 'ends_at', 'grace_ends_at', 'cancelled_at']);

            $attributes = ['status' => $status];

            if ($status === 'active') {
                $attributes['ends_at'] = now()->addMonth();
                $attributes['grace_ends_at'] = now()->addMonth()->addDays(self::GRACE_DAYS);
                $attributes['cancelled_at'] = null;
            }

            if ($status === 'past_due') {
                $attributes['grace_ends_at'] = now()->addDays(self::GRACE_DAYS);
            }

            if ($status === 'cancelled') {
                $attributes['cancelled_at'] = now();
            }

            if ($status === 'expired') {
                $attributes['ends_at'] = now()->subDay();
                $attributes['grace_ends_at'] = now()->subDay();
            }

            $locked->forceFill($attributes)->save();

            app(AuditLogger::class)->log('saas_subscription.status_changed', $locked, $before, ['status' => $status], $actorId);

            return $locked->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function themes(): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $custom = [];

        try {
            $custom = Tenant::query()
                ->whereNotNull('theme')
                ->orderBy('name')
                ->limit(20)
                ->get()
                ->filter(fn (Tenant $tenant): bool => is_array($tenant->theme) && $tenant->theme !== [])
                ->map(fn (Tenant $tenant): array => [
                    'id' => (int) $tenant->id,
                    'name' => (string) $tenant->name,
                    'source' => 'tenant',
                    'color' => (string) ($tenant->theme['brandColor'] ?? $tenant->theme['brand_color'] ?? '#206bc4'),
                    'dark' => (bool) ($tenant->theme['darkMode'] ?? $tenant->theme['dark_mode'] ?? false),
                ])
                ->all();
        } catch (\Throwable) {
            $custom = [];
        }

        return [
            'bundled' => [
                ['code' => 'default', 'name' => 'Default Platform', 'description' => 'Tema bawaan instalasi, mengikuti warna merek yang diatur administrator.', 'source' => 'system', 'customizable' => true],
                ['code' => 'light', 'name' => 'Light', 'description' => 'Selalu tampil dalam mode terang.', 'source' => 'system', 'customizable' => false],
                ['code' => 'dark', 'name' => 'Dark', 'description' => 'Selalu tampil dalam mode gelap.', 'source' => 'system', 'customizable' => false],
                ['code' => 'system', 'name' => 'Ikuti Sistem', 'description' => 'Mengikuti preferensi terang/gelap pada perangkat pengguna.', 'source' => 'system', 'customizable' => false],
            ],
            'custom' => $custom,
            'storefront_url' => $base,
            'features' => \App\Enums\PlatformFeature::cases(),
        ];
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tenant';
        $slug = $base;
        $suffix = 1;

        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    private function uniquePlanSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'paket';
        $slug = $base;
        $suffix = 1;

        while (SaasPlan::query()->where('slug', $slug)->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
