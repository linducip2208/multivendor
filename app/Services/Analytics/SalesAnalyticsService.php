<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Support\Currency;
use Illuminate\Support\Facades\DB;

/**
 * Sales performance: trend, channel mix, payment mix and a paginated order
 * ledger. The controller only receives shaped arrays.
 */
final class SalesAnalyticsService extends AnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function report(DateRange $range, int $perPage = 20, int $page = 1, string $search = ''): array
    {
        return $this->remember('sales', $range, function () use ($range, $perPage, $page, $search): array {
            $previous = $range->previous();

            $gmv = (float) $this->revenueOrders($range)->sum('total');
            $previousGmv = (float) $this->revenueOrders($previous)->sum('total');
            $count = (int) $this->revenueOrders($range)->count();
            $previousCount = (int) $this->revenueOrders($previous)->count();
            $aov = $count > 0 ? $gmv / $count : 0.0;
            $previousAov = $previousCount > 0 ? $previousGmv / $previousCount : 0.0;

            $units = (int) $this->revenueOrders($range)
                ->join('order_items', 'order_items.order_id', '=', 'orders.id')
                ->sum('order_items.quantity');
            return [
                'range' => $range->toArray(),
                'kpis' => [
                    'gmv' => ['value' => $gmv, 'label' => 'GMV', 'money' => true, 'trend' => $this->delta($gmv, $previousGmv)],
                    'orders' => ['value' => $count, 'label' => 'Pesanan', 'money' => false, 'trend' => $this->delta((float) $count, (float) $previousCount)],
                    'aov' => ['value' => $aov, 'label' => 'Rata-rata Order', 'money' => true, 'trend' => $this->delta($aov, $previousAov)],
                    'units' => ['value' => $units, 'label' => 'Unit Terjual', 'money' => false, 'trend' => $this->delta((float) $units, 0.0)],
                    'cancelled' => [
                        'value' => (int) $this->orders($range)->whereIn('order_status', self::failedOrderStatuses())->count(),
                        'label' => 'Dibatalkan',
                        'money' => false,
                        'trend' => $this->delta(0.0, 0.0),
                    ],
                ],
                'series' => $this->trendSeries($range),
                'payment_mix' => $this->grouped($this->revenueOrders($range), 'payment_method', 'total'),
                'status_mix' => $this->grouped($this->orders($range), 'order_status', 'total'),
                'shop_mix' => $this->grouped($this->revenueOrders($range), 'shop_id', 'total', 10),
                'orders' => $this->orderLedger($range, $perPage, $page, $search),
            ];
        }, ['per_page' => $perPage, 'page' => $page, 'search' => $search]);
    }

    /**
     * @return array{labels: list<string>, gmv: list<float>, orders: list<float>, refunds: list<float>}
     */
    public function trendSeries(DateRange $range): array
    {
        $expression = $this->dateExpression(DB::connection()->getDriverName());
        $labels = $range->labels();

        $rows = DB::table('orders')
            ->whereIn('order_status', self::revenueOrderStatuses())
            ->whereIn('payment_status', self::paidPaymentStatuses())
            ->whereBetween('created_at', [$range->from, $range->to])
            ->selectRaw($expression.' as bucket, SUM(total) as amount, COUNT(*) as orders')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $amounts = [];
        $counts = [];
        foreach ($rows as $row) {
            $amounts[(string) $row->bucket] = (float) $row->amount;
            $counts[(string) $row->bucket] = (int) $row->orders;
        }

        $refunds = [];
        if ($this->has('refunds')) {
            $refundRows = DB::table('refunds')
                ->where('status', 'succeeded')
                ->whereBetween('succeeded_at', [$range->from, $range->to])
                ->selectRaw($expression.' as bucket, SUM(amount) as amount')
                ->groupBy('bucket')
                ->get();

            foreach ($refundRows as $row) {
                $refunds[(string) $row->bucket] = (float) $row->amount;
            }
        }

        return [
            'labels' => $labels,
            'gmv' => $this->densify($labels, $amounts),
            'orders' => $this->densify($labels, $counts, 'orders'),
            'refunds' => $this->densify($labels, $refunds),
            'step' => $range->step(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function orderLedger(DateRange $range, int $perPage, int $page, string $search = ''): array
    {
        $query = $this->orders($range)->with(['customer:id,name,email', 'shop:id,name']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('order_number', 'like', '%'.$search.'%')
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$search.'%'));
            });
        }

        $total = (clone $query)->count();
        $perPage = max(5, min(100, $perPage));
        $page = max(1, $page);
        $rows = $query->orderByDesc('created_at')
            ->forPage($page, $perPage)
            ->get()
            ->map(function (\App\Models\Order $order): array {
                $state = OrderStatus::fromStored((string) $order->order_status);
                $payment = PaymentStatus::tryFrom((string) $order->payment_status);

                return [
                    'id' => (int) $order->id,
                    'order_number' => (string) $order->order_number,
                    'customer' => (string) ($order->customer?->name ?? '-'),
                    'shop' => (string) ($order->shop?->name ?? '-'),
                    'order_status' => (string) $order->order_status,
                    'order_status_label' => $state->label(),
                    'order_status_badge' => $state->badge(),
                    'payment_status' => (string) $order->payment_status,
                    'payment_status_label' => $payment?->label() ?? (string) $order->payment_status,
                    'payment_status_badge' => $payment?->badge() ?? 'gray',
                    'total' => (float) $order->total,
                    'total_formatted' => Currency::format((float) $order->total),
                    'created_at' => (string) ($order->created_at?->format('Y-m-d H:i') ?? ''),
                    'url' => route('admin.orders.show', (int) $order->id),
                ];
            })
            ->all();

        return [
            'rows' => $rows,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<float>, counts: list<int>}
     */
    private function grouped(\Illuminate\Database\Eloquent\Builder $query, string $column, string $sum, int $limit = 0): array
    {
        $builder = clone $query;
        $builder->select($column, DB::raw('COALESCE(SUM('.$sum.'), 0) as total'), DB::raw('COUNT(*) as orders'))
            ->groupBy($column)
            ->orderByDesc('total');

        if ($limit > 0) {
            $builder->limit($limit);
        }

        $shopNames = [];

        if ($column === 'shop_id' && $this->has('shops')) {
            $ids = $builder->pluck('shop_id')->filter()->map(fn ($id) => (int) $id)->all();
            $shopNames = $ids === []
                ? []
                : \App\Models\Shop::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
        }

        $labels = [];
        $values = [];
        $counts = [];

        foreach ($builder->get() as $row) {
            $key = $row->{$column};
            $labels[] = match (true) {
                $key === null || $key === '' => 'Tidak ada',
                $column === 'shop_id' => (string) ($shopNames[(int) $key] ?? '#'.$key),
                $column === 'payment_method' => (string) $key,
                default => \Illuminate\Support\Str::headline((string) $key),
            };
            $values[] = (float) $row->total;
            $counts[] = (int) $row->orders;
        }

        return ['labels' => $labels, 'values' => $values, 'counts' => $counts];
    }

    private function dateExpression(string $driver): string
    {
        return match ($driver) {
            'sqlite' => 'date(created_at)',
            'pgsql' => "to_char(created_at, 'YYYY-MM-DD')",
            default => 'DATE(created_at)',
        };
    }
}
