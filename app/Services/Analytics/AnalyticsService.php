<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Shared query vocabulary for every analytics report.
 *
 * Controllers and Blade templates never build a money expression themselves:
 * they ask one of the report services, which owns the definitions of what
 * counts as a sale, a refund and a commission. Results are memoised for a short
 * window so opening a dashboard with eight tiles costs one query, not eight.
 */
abstract class AnalyticsService
{
    public const CACHE_TTL = 300;

    /**
     * Order states that represent real commerce. A cancelled or failed order is
     * excluded from every revenue figure.
     *
     * @return list<string>
     */
    public static function revenueOrderStatuses(): array
    {
        return [
            OrderStatus::fromStored('confirmed')->stored(),
            OrderStatus::Processing->stored(),
            OrderStatus::Packed->stored(),
            OrderStatus::Shipped->stored(),
            OrderStatus::Delivered->stored(),
            OrderStatus::Completed->stored(),
            OrderStatus::ReturnRequested->stored(),
            OrderStatus::Returned->stored(),
            OrderStatus::RefundPending->stored(),
            OrderStatus::Refunded->stored(),
        ];
    }

    /** @return list<string> */
    public static function paidPaymentStatuses(): array
    {
        return [
            PaymentStatus::Paid->value,
            PaymentStatus::Partial->value,
            PaymentStatus::Refunded->value,
        ];
    }

    /** @return list<string> */
    public static function failedOrderStatuses(): array
    {
        return [
            OrderStatus::Cancelled->stored(),
            OrderStatus::Failed->stored(),
        ];
    }

    /** @return list<string> */
    public static function successfulTransactionStatuses(): array
    {
        return ['success', 'refunded'];
    }

    protected function orders(DateRange $range): Builder
    {
        return \App\Models\Order::query()
            ->whereBetween('orders.created_at', [$range->from, $range->to]);
    }

    protected function revenueOrders(DateRange $range): Builder
    {
        return $this->orders($range)
            ->whereIn('order_status', self::revenueOrderStatuses())
            ->whereIn('payment_status', self::paidPaymentStatuses());
    }

    /**
     * @template TValue
     *
     * @param  callable():TValue  $resolver
     * @return TValue
     */
    protected function remember(string $prefix, DateRange $range, callable $resolver, array $extra = []): mixed
    {
        try {
            return Cache::remember($range->key($prefix, $extra), self::CACHE_TTL, $resolver);
        } catch (\Throwable) {
            return $resolver();
        }
    }

    /**
     * Percentage change, guarding the "previous period was zero" division.
     *
     * @return array{value: float, direction: string, previous: float, current: float}
     */
    protected function delta(float $current, float $previous): array
    {
        if ($previous == 0.0) {
            $direction = $current > 0 ? 'up' : ($current < 0 ? 'down' : 'flat');
            $value = $current > 0 ? 100.0 : 0.0;
        } else {
            $value = (($current - $previous) / abs($previous)) * 100;
            $direction = $value > 0.05 ? 'up' : ($value < -0.05 ? 'down' : 'flat');
        }

        return [
            'value' => round($value, 1),
            'direction' => $direction,
            'current' => round($current, 2),
            'previous' => round($previous, 2),
        ];
    }

    protected function has(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Turn a database DATE() bucket map into a dense, chart-ready series.
     *
     * @param  array<string, float|int>  $buckets
     * @return list<float>
     */
    protected function densify(array $labels, array $buckets, string $key = 'total'): array
    {
        $values = [];

        foreach ($labels as $label) {
            $value = $buckets[$label] ?? 0;
            $values[] = is_array($value) ? (float) ($value[$key] ?? 0) : (float) $value;
        }

        return $values;
    }
}
