<?php

declare(strict_types=1);

namespace App\Services\Vendor;

use App\Models\Shop;
use App\Models\SubscriptionPlan;
use App\Models\VendorSubscription;
use App\Services\AuditLogger;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plan entitlements and the subscription lifecycle.
 *
 * A plan is the ceiling on everything a shop may consume; a subscription is the
 * window in which those ceilings apply. Both are read here so a product create,
 * a staff invite and an inventory page all agree on what "over quota" means.
 */
final class VendorSubscriptionService
{
    private const ACTIVE = ['active', 'trialing', 'grace'];

    public function __construct(private readonly VendorScope $scope) {}

    /** @return list<array<string, mixed>> */
    public function catalogue(): array
    {
        return SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get()
            ->map(fn (SubscriptionPlan $plan): array => [
                'id' => (int) $plan->getKey(),
                'name' => (string) $plan->name,
                'slug' => (string) $plan->slug,
                'description' => $plan->description,
                'price' => Money::of($plan->price),
                'billing_cycle' => (string) $plan->billing_cycle,
                'billing_days' => (int) $plan->billing_days,
                'trial_days' => (int) ($plan->trial_days ?? 0),
                'grace_days' => (int) ($plan->grace_days ?? 0),
                'grace_allowed' => (bool) $plan->grace_allowed,
                'is_featured' => (bool) $plan->is_featured,
                'entitlements' => $this->entitlements($plan),
                'commission' => [
                    'type' => (string) ($plan->commission_type ?? 'percentage'),
                    'value' => Money::of($plan->commission_value ?? 0),
                    'tier' => $plan->commission_tier,
                ],
            ])->all();
    }

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $shop = $this->scope->shop();
        $subscription = $this->current();
        $plan = $subscription?->plan;
        $usage = $this->usage($shop);
        $entitlements = $plan !== null ? $this->entitlements($plan) : $this->unlimited();

        return [
            'shop' => $shop,
            'subscription' => $subscription,
            'plan' => $plan,
            'status' => $this->statusLabel($subscription),
            'entitlements' => $entitlements,
            'usage' => $usage,
            'consumption' => $this->consumption($usage, $entitlements),
            'plans' => $this->catalogue(),
            'current_plan_id' => $plan !== null ? (int) $plan->getKey() : null,
        ];
    }

    public function current(): ?VendorSubscription
    {
        return VendorSubscription::query()
            ->with('plan')
            ->where('shop_id', $this->scope->shopId())
            ->whereIn('status', self::ACTIVE)
            ->orderByDesc('ends_at')
            ->orderByDesc('id')
            ->first();
    }

    /** @return array<string, int> */
    public function entitlements(SubscriptionPlan $plan): array
    {
        return [
            'products' => (int) ($plan->max_products ?? 0),
            'staff' => (int) ($plan->max_staff ?? 0),
            'storage_mb' => (int) ($plan->max_storage_mb ?? 0),
            'transactions' => (int) ($plan->max_monthly_transactions ?? 0),
            'shops' => (int) ($plan->max_shop_limit ?? 0),
        ];
    }

    /** @return array<string, int> */
    public function unlimited(): array
    {
        return ['products' => 0, 'staff' => 0, 'storage_mb' => 0, 'transactions' => 0, 'shops' => 0];
    }

    public function limit(string $key): int
    {
        $plan = $this->current()?->plan;

        if ($plan === null) {
            return 0;
        }

        return $this->entitlements($plan)[$key] ?? 0;
    }

    /** 0 means unlimited. */
    public function allows(string $key, int $additional = 1): bool
    {
        $limit = $this->limit($key);

        if ($limit <= 0) {
            return true;
        }

        return $this->usage($this->scope->shop())[$key] + $additional <= $limit;
    }

    public function assertAllows(string $key, int $additional = 1, string $label = 'item'): void
    {
        if ($this->allows($key, $additional)) {
            return;
        }

        $plan = $this->current()?->plan;

        throw ValidationException::withMessages([
            $key => 'Kuota paket '.($plan?->name ?? 'saat ini').' sudah tercapai. Upgrade paket untuk menambah '.$label.'.',
        ]);
    }

    /** @return array<string, int> */
    public function usage(Shop $shop): array
    {
        $monthStart = CarbonImmutable::now()->startOfMonth();

        $transactions = 0;

        try {
            $transactions = (int) DB::table('wallet_transactions')
                ->join('wallets', 'wallets.id', '=', 'wallet_transactions.wallet_id')
                ->where('wallets.user_id', $shop->vendor_id)
                ->where('wallet_transactions.created_at', '>=', $monthStart)
                ->count();
        } catch (\Throwable) {
            $transactions = 0;
        }

        $storageBytes = 0;

        try {
            $storageBytes = (int) DB::table('products')
                ->where('shop_id', $shop->getKey())
                ->sum(DB::raw('COALESCE(LENGTH(COALESCE(thumbnail, \'\')), 0) + COALESCE(LENGTH(COALESCE(description, \'\')), 0)'));
        } catch (\Throwable) {
            $storageBytes = 0;
        }

        return [
            'products' => (int) $shop->products()->count(),
            'staff' => (int) $this->staffCount($shop->getKey()),
            'storage_mb' => (int) round($storageBytes / 1048576),
            'transactions' => $transactions,
            'shops' => 1,
        ];
    }

    /** @param  array<string, int>  $usage @param  array<string, int>  $entitlements */
    public function consumption(array $usage, array $entitlements): array
    {
        $out = [];

        foreach ($entitlements as $key => $limit) {
            $used = $usage[$key] ?? 0;
            $out[$key] = [
                'used' => $used,
                'limit' => $limit,
                'percent' => $limit > 0 ? min(100, (int) round(($used / $limit) * 100)) : 0,
                'unlimited' => $limit <= 0,
            ];
        }

        return $out;
    }

    public function subscribe(int $planId, array $payload = []): VendorSubscription
    {
        return DB::transaction(function () use ($planId, $payload): VendorSubscription {
            $plan = SubscriptionPlan::query()->lockForUpdate()->find($planId);

            if ($plan === null || ! $plan->is_active) {
                throw ValidationException::withMessages(['plan_id' => 'Paket langganan tidak tersedia.']);
            }

            $now = CarbonImmutable::now();
            $existing = VendorSubscription::query()
                ->where('shop_id', $this->scope->shopId())
                ->whereIn('status', self::ACTIVE)
                ->lockForUpdate()
                ->orderByDesc('ends_at')
                ->first();

            $startsAt = $existing !== null && $existing->ends_at?->isFuture()
                ? $existing->ends_at
                : $now;

            $trialEndsAt = null;

            if ((int) $plan->trial_days > 0) {
                $trialEndsAt = $startsAt->addDays((int) $plan->trial_days);
            }

            $endsAt = $trialEndsAt ?? $startsAt->addDays(max(1, (int) $plan->billing_days));

            if ($existing !== null) {
                $this->closePrevious($existing, $now, $payload);
            }

            $subscription = VendorSubscription::query()->create([
                'vendor_id' => $this->scope->userId(),
                'shop_id' => $this->scope->shopId(),
                'subscription_plan_id' => $plan->getKey(),
                'status' => $trialEndsAt !== null ? 'trialing' : 'active',
                'amount_paid' => $trialEndsAt !== null ? Money::zero()->toDecimal() : Money::of($plan->price)->toDecimal(),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'payment_method' => VendorScope::cleanNullable($payload['payment_method'] ?? 'manual', 40),
                'transaction_reference' => VendorScope::cleanNullable($payload['transaction_reference'] ?? null, 120),
            ]);

            $this->extend($subscription, [
                'trial_ends_at' => $trialEndsAt,
                'grace_ends_at' => null,
                'auto_renew' => (bool) ($payload['auto_renew'] ?? true),
                'renewed_from_id' => $existing?->getKey(),
            ]);

            $this->applyCommissionTier($plan);

            app(AuditLogger::class)->log('vendor.subscription.started', $subscription, [], [
                'plan_id' => $plan->getKey(),
                'starts_at' => $startsAt->toDateTimeString(),
                'ends_at' => $endsAt->toDateTimeString(),
            ], $this->scope->userId());

            return $subscription->refresh()->load('plan');
        }, 3);
    }

    public function renew(): VendorSubscription
    {
        $current = $this->current();

        if ($current === null || $current->plan === null) {
            throw ValidationException::withMessages(['plan_id' => 'Tidak ada langganan aktif untuk diperpanjang.']);
        }

        return $this->subscribe((int) $current->plan->getKey(), [
            'payment_method' => $current->payment_method,
            'auto_renew' => $current->auto_renew,
            'transaction_reference' => $current->transaction_reference,
        ]);
    }

    public function cancel(string $reason): VendorSubscription
    {
        return DB::transaction(function () use ($reason): VendorSubscription {
            $subscription = VendorSubscription::query()
                ->with('plan')
                ->where('shop_id', $this->scope->shopId())
                ->whereIn('status', self::ACTIVE)
                ->lockForUpdate()
                ->orderByDesc('ends_at')
                ->first();

            if ($subscription === null) {
                throw ValidationException::withMessages(['subscription' => 'Tidak ada langganan aktif.']);
            }

            $subscription->extend([
                'status' => 'expired',
                'canceled_at' => now(),
                'cancel_reason' => VendorScope::clean($reason, 255),
                'auto_renew' => false,
            ])->save();

            app(AuditLogger::class)->log('vendor.subscription.cancelled', $subscription, [
                'status' => 'active',
            ], [
                'status' => 'expired',
                'reason' => VendorScope::clean($reason, 255),
            ], $this->scope->userId());

            return $subscription->refresh();
        }, 3);
    }

    /**
     * Expire a lapsed subscription and decide whether the shop keeps trading
     * during a grace window. Returns the subscription when it changed.
     */
    public function reconcile(): ?VendorSubscription
    {
        return DB::transaction(function (): ?VendorSubscription {
            $subscription = VendorSubscription::query()
                ->with('plan')
                ->where('shop_id', $this->scope->shopId())
                ->whereIn('status', self::ACTIVE)
                ->lockForUpdate()
                ->orderByDesc('ends_at')
                ->first();

            if ($subscription === null || $subscription->ends_at === null) {
                return null;
            }

            $now = CarbonImmutable::now();

            if ($subscription->ends_at->isFuture()) {
                return null;
            }

            $graceDays = (int) ($subscription->plan?->grace_days ?? 0);
            $graceAllowed = (bool) ($subscription->plan?->grace_allowed ?? false);
            $graceEndsAt = $subscription->ends_at->addDays($graceDays);

            $status = $graceAllowed && $graceDays > 0 && $now->lessThan($graceEndsAt)
                ? 'grace'
                : 'expired';

            $subscription->extend([
                'status' => $status,
                'grace_ends_at' => $graceAllowed && $graceDays > 0 ? $graceEndsAt : null,
            ])->save();

            app(AuditLogger::class)->log('vendor.subscription.expired', $subscription, [
                'status' => 'active',
            ], [
                'status' => $status,
                'grace_ends_at' => $subscription->grace_ends_at?->toDateTimeString(),
            ], $this->scope->userId());

            return $subscription->refresh();
        }, 3);
    }

    private function closePrevious(VendorSubscription $existing, CarbonImmutable $now, array $payload): void
    {
        $isUpgrade = $this->isUpgrade((int) $existing->subscription_plan_id, (int) ($payload['plan_id'] ?? 0));

        $existing->extend([
            'status' => 'expired',
            'ends_at' => $now,
            'downgrade_at_period_end' => ! $isUpgrade,
        ])->save();
    }

    /** Columns added after the model was authored, written defensively. */
    private function extend(VendorSubscription $subscription, array $attributes): VendorSubscription
    {
        foreach ($attributes as $key => $value) {
            if (Schema::hasColumn('vendor_subscriptions', (string) $key)) {
                $subscription->setAttribute($key, $value);
            }
        }

        return $subscription;
    }

    private function isUpgrade(int $currentPlanId, int $targetPlanId): bool
    {
        if ($currentPlanId === 0 || $targetPlanId === 0 || $currentPlanId === $targetPlanId) {
            return false;
        }

        $current = SubscriptionPlan::query()->find($currentPlanId);
        $target = SubscriptionPlan::query()->find($targetPlanId);

        if ($current === null || $target === null) {
            return false;
        }

        return Money::of($target->price)->compare(Money::of($current->price)) > 0;
    }

    private function applyCommissionTier(SubscriptionPlan $plan): void
    {
        if ($plan->commission_tier === null || $plan->commission_value === null) {
            return;
        }

        $shop = Shop::query()->lockForUpdate()->findOrFail($this->scope->shopId());

        $shop->forceFill([
            'commission_type' => (string) ($plan->commission_type ?? 'percentage'),
            'commission_value' => Money::of($plan->commission_value)->toDecimal(),
        ])->save();
    }

    private function staffCount(int $shopId): int
    {
        try {
            return (int) DB::table('shop_staff')
                ->where('shop_id', $shopId)
                ->where('is_active', true)
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function statusLabel(?VendorSubscription $subscription): array
    {
        if ($subscription === null) {
            return ['key' => 'none', 'label' => 'Tanpa Paket', 'badge' => 'secondary'];
        }

        return match ((string) $subscription->status) {
            'trialing' => ['key' => 'trialing', 'label' => 'Masa Uji Coba', 'badge' => 'info'],
            'grace' => ['key' => 'grace', 'label' => 'Masa Tenggang', 'badge' => 'warning'],
            'expired' => ['key' => 'expired', 'label' => 'Kedaluwarsa', 'badge' => 'danger'],
            default => ['key' => 'active', 'label' => 'Aktif', 'badge' => 'success'],
        };
    }
}
