<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Models\CustomerSegment;
use App\Models\CustomerSegmentMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Behavioural segmentation.
 *
 * Every segment is defined by counts and sums over recorded facts: how many
 * orders, how much spent, how long since the last one, whether a coupon was
 * used, which categories were bought. Nothing here infers intent, health,
 * income or personality, and the eight built-in types are named after the
 * behaviour they measure rather than after a trait of the person.
 */
final class SegmentationService
{
    public const TYPE_MANUAL = 'manual';
    public const TYPE_RULE = 'rule';

    public const TYPES = [
        self::TYPE_MANUAL => 'Manual',
        self::TYPE_RULE => 'Berbasis Aturan',
    ];

    /**
     * The selectable behaviour set. `label` is what an operator sees; `rule` is
     * the machine definition.
     *
     * @return array<string, array{label: string, description: string, rules: array<string, mixed>}>
     */
    public static function presets(): array
    {
        return [
            'new' => [
                'label' => 'Pembeli Baru',
                'description' => 'Pelanggan yang pertama kali tercatat melakukan pesanan dalam 30 hari terakhir.',
                'rules' => ['max_order_count' => 1, 'within_days' => 30],
            ],
            'active' => [
                'label' => 'Aktif Membeli',
                'description' => 'Pelanggan dengan minimal 3 pesanan dalam 90 hari terakhir.',
                'rules' => ['min_order_count' => 3, 'within_days' => 90],
            ],
            'inactive' => [
                'label' => 'Tidak Aktif',
                'description' => 'Pelanggan yang sudah pernah membeli tetapi tidak memesan lagi selama 90 hari.',
                'rules' => ['min_order_count' => 1, 'inactive_days' => 90],
            ],
            'high_value' => [
                'label' => 'Nilai Tinggi',
                'description' => 'Pelanggan dengan total belanja di atas ambang yang ditentukan operator.',
                'rules' => ['min_lifetime_value' => 1000000],
            ],
            'at_risk' => [
                'label' => 'Berisiko Churn',
                'description' => 'Pelanggan dengan 3+ pesanan historis tetapi tidak belanja lagi selama 60 hari.',
                'rules' => ['min_order_count' => 3, 'inactive_days' => 60],
            ],
            'repeat' => [
                'label' => 'Pembeli Berulang',
                'description' => 'Pelanggan dengan minimal 2 pesanan berhasil.',
                'rules' => ['min_order_count' => 2],
            ],
            'coupon_sensitive' => [
                'label' => 'Pengguna Kupon',
                'description' => 'Pelanggan yang memakai kupon pada mayoritas pesanannya.',
                'rules' => ['min_coupon_ratio' => 0.5, 'min_order_count' => 2],
            ],
            'category_affinity' => [
                'label' => 'Minat Kategori',
                'description' => 'Pelanggan yang rutin membeli satu kategori tertentu.',
                'rules' => ['min_category_ratio' => 0.6, 'min_order_count' => 2],
            ],
        ];
    }

    /**
     * Supported rule keys for validation and for the operator help text.
     *
     * @return list<array{key: string, type: string, label: string}>
     */
    public static function ruleCatalogue(): array
    {
        return [
            ['key' => 'min_order_count', 'type' => 'integer', 'label' => 'Minimal jumlah pesanan'],
            ['key' => 'max_order_count', 'type' => 'integer', 'label' => 'Maksimal jumlah pesanan'],
            ['key' => 'min_lifetime_value', 'type' => 'number', 'label' => 'Minimal total belanja'],
            ['key' => 'max_lifetime_value', 'type' => 'number', 'label' => 'Maksimal total belanja'],
            ['key' => 'within_days', 'type' => 'integer', 'label' => 'Pesanan dalam N hari terakhir'],
            ['key' => 'inactive_days', 'type' => 'integer', 'label' => 'Tidak belanja selama N hari'],
            ['key' => 'min_coupon_ratio', 'type' => 'number', 'label' => 'Rasio pesanan memakai kupon (0-1)'],
            ['key' => 'min_category_ratio', 'type' => 'number', 'label' => 'Rasio satu kategori (0-1)'],
            ['key' => 'category_id', 'type' => 'integer', 'label' => 'Kategori favorit'],
            ['key' => 'shop_id', 'type' => 'integer', 'label' => 'Toko favorit'],
            ['key' => 'registered_within_days', 'type' => 'integer', 'label' => 'Terdaftar dalam N hari terakhir'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        return CustomerSegment::query()
            ->withCount('members')
            ->orderByDesc('member_count')
            ->orderBy('name')
            ->get()
            ->map(fn (CustomerSegment $segment): array => [
                'id' => (int) $segment->id,
                'name' => (string) $segment->name,
                'slug' => (string) $segment->slug,
                'description' => (string) ($segment->description ?? ''),
                'type' => (string) $segment->type,
                'is_dynamic' => (bool) $segment->is_dynamic,
                'member_count' => (int) $segment->member_count,
                'rules' => $segment->rules,
                'rule_summary' => $this->describeRules(is_array($segment->rules) ? $segment->rules : []),
            ])
            ->all();
    }

    /**
     * Recompute membership for a rule segment from real order history.
     *
     * Membership is rebuilt rather than merged: a customer who no longer meets
     * the rule is removed, so the stored count always matches the rule.
     *
     * @return array{segment: array<string, mixed>, added: int, removed: int, matched: int, evaluated: int}
     */
    public function sync(CustomerSegment $segment): array
    {
        $rules = is_array($segment->rules) ? $segment->rules : [];
        $facts = $this->facts($rules);
        $matched = array_keys($facts);

        $before = CustomerSegmentMember::query()
            ->where('customer_segment_id', $segment->id)
            ->pluck('customer_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $removed = array_diff($before, $matched);
        $added = array_diff($matched, $before);

        DB::transaction(function () use ($segment, $matched, $facts, $removed): void {
            if ($removed !== []) {
                CustomerSegmentMember::query()
                    ->where('customer_segment_id', $segment->id)
                    ->whereIn('customer_id', $removed)
                    ->delete();
            }

            $now = now();
            $existing = array_flip($matched);

            $rows = [];
            foreach ($matched as $customerId) {
                if (in_array($customerId, $removed, true)) {
                    continue;
                }
                $fact = $facts[$customerId];
                $rows[] = [
                    'customer_segment_id' => $segment->id,
                    'customer_id' => $customerId,
                    'lifetime_value' => $fact['lifetime_value'],
                    'order_count' => $fact['order_count'],
                    'last_order_at' => $fact['last_order_at'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                CustomerSegmentMember::query()->upsert($chunk, ['customer_segment_id', 'customer_id'], ['lifetime_value', 'order_count', 'last_order_at', 'updated_at']);
            }

            unset($existing);

            $segment->forceFill([
                'member_count' => count($matched),
                'is_dynamic' => true,
            ])->save();
        });

        return [
            'segment' => [
                'id' => (int) $segment->id,
                'name' => (string) $segment->name,
                'slug' => (string) $segment->slug,
                'member_count' => count($matched),
            ],
            'added' => count($added),
            'removed' => count($removed),
            'matched' => count($matched),
            'evaluated' => count($facts),
        ];
    }

    /**
     * Per-customer aggregates every rule can be evaluated against.
     *
     * @return array<int, array{lifetime_value: float, order_count: int, last_order_at: ?string, coupon_ratio: float, category_ratio: float, category_id: ?int, shop_id: ?int}>
     */
    private function facts(array $rules): array
    {
        $window = isset($rules['within_days']) ? max(1, (int) $rules['within_days']) : null;

        $rows = DB::table('orders')
            ->leftJoin('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.customer_id', '>', 0)
            ->whereIn('orders.payment_status', ['paid', 'partial', 'refunded'])
            ->whereNotIn('orders.order_status', ['canceled', 'failed'])
            ->when($window !== null, fn ($q) => $q->where('orders.created_at', '>=', now()->subDays($window)))
            ->groupBy('orders.id', 'orders.customer_id', 'orders.total', 'orders.coupon_code', 'orders.created_at', 'orders.shop_id', 'order_items.category_id')
            ->selectRaw('orders.customer_id, orders.total, orders.coupon_code, orders.created_at, orders.shop_id, order_items.category_id, COUNT(order_items.id) as line_items')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            $customerId = (int) $row->customer_id;
            $totals[$customerId] ??= [
                'lifetime_value' => 0.0,
                'order_count' => 0,
                'coupon_orders' => 0,
                'last_order_at' => null,
                'categories' => [],
                'shops' => [],
            ];

            $spend = (float) $row->total;
            $totals[$customerId]['lifetime_value'] += $spend;
            $totals[$customerId]['order_count']++;
            $totals[$customerId]['coupon_orders'] += ($row->coupon_code !== null && $row->coupon_code !== '') ? 1 : 0;

            $timestamp = (string) $row->created_at;
            if ($totals[$customerId]['last_order_at'] === null || $timestamp > $totals[$customerId]['last_order_at']) {
                $totals[$customerId]['last_order_at'] = $timestamp;
            }

            if ($row->category_id !== null) {
                $totals[$customerId]['categories'][(int) $row->category_id] = ($totals[$customerId]['categories'][(int) $row->category_id] ?? 0) + (int) $row->line_items;
            }

            if ($row->shop_id !== null) {
                $totals[$customerId]['shops'][(int) $row->shop_id] = ($totals[$customerId]['shops'][(int) $row->shop_id] ?? 0) + 1;
            }
        }

        $registeredWithin = isset($rules['registered_within_days'])
            ? User::query()->where('created_at', '>=', now()->subDays(max(1, (int) $rules['registered_within_days'])))->pluck('id')->map(fn ($id): int => (int) $id)->all()
            : null;

        $out = [];

        foreach ($totals as $customerId => $fact) {
            if ($registeredWithin !== null && ! in_array($customerId, $registeredWithin, true)) {
                continue;
            }

            $out[$customerId] = [
                'lifetime_value' => round($fact['lifetime_value'], 2),
                'order_count' => $fact['order_count'],
                'last_order_at' => $fact['last_order_at'],
                'coupon_ratio' => $fact['order_count'] > 0 ? $fact['coupon_orders'] / $fact['order_count'] : 0.0,
                'category_ratio' => $this->concentration($fact['categories']),
                'category_id' => $this->topKey($fact['categories']),
                'shop_id' => $this->topKey($fact['shops']),
            ];
        }

        return array_filter($out, fn (array $fact, int $customerId): bool => $this->matches($fact, $rules), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @param  array<string, mixed>  $fact
     * @param  array<string, mixed>  $rules
     */
    private function matches(array $fact, array $rules): bool
    {
        if (isset($rules['min_order_count']) && $fact['order_count'] < (int) $rules['min_order_count']) {
            return false;
        }

        if (isset($rules['max_order_count']) && $fact['order_count'] > (int) $rules['max_order_count']) {
            return false;
        }

        if (isset($rules['min_lifetime_value']) && $fact['lifetime_value'] < (float) $rules['min_lifetime_value']) {
            return false;
        }

        if (isset($rules['max_lifetime_value']) && $fact['lifetime_value'] > (float) $rules['max_lifetime_value']) {
            return false;
        }

        if (isset($rules['inactive_days'])) {
            $last = $fact['last_order_at'] === null ? null : \Illuminate\Support\Carbon::parse($fact['last_order_at']);
            if ($last !== null && $last->diffInDays(now()) < (int) $rules['inactive_days']) {
                return false;
            }
        }

        if (isset($rules['min_coupon_ratio']) && $fact['coupon_ratio'] < (float) $rules['min_coupon_ratio']) {
            return false;
        }

        if (isset($rules['min_category_ratio']) && $fact['category_ratio'] < (float) $rules['min_category_ratio']) {
            return false;
        }

        if (isset($rules['category_id']) && (int) $fact['category_id'] !== (int) $rules['category_id']) {
            return false;
        }

        if (isset($rules['shop_id']) && (int) $fact['shop_id'] !== (int) $rules['shop_id']) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<int, int>  $buckets
     */
    private function concentration(array $buckets): float
    {
        $total = array_sum($buckets);

        if ($total <= 0) {
            return 0.0;
        }

        return round(max($buckets) / $total, 4);
    }

    /**
     * @param  array<int, int>  $buckets
     */
    private function topKey(array $buckets): ?int
    {
        if ($buckets === []) {
            return null;
        }

        arsort($buckets);

        return (int) array_key_first($buckets);
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public function describeRules(array $rules): string
    {
        if ($rules === []) {
            return 'Tanpa aturan (anggota dipilih manual)';
        }

        $labels = [];
        foreach (self::ruleCatalogue() as $rule) {
            if (isset($rules[$rule['key']])) {
                $labels[] = $rule['label'].': '.$rules[$rule['key']];
            }
        }

        return $labels === [] ? 'Tanpa aturan' : implode(' · ', $labels);
    }

    public function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'segmen';
        $slug = $base;
        $suffix = 1;

        while (CustomerSegment::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    /**
     * @return Collection<int, CustomerSegmentMember>
     */
    public function members(int $segmentId, int $limit = 50): Collection
    {
        return CustomerSegmentMember::query()
            ->with('customer:id,name,email')
            ->where('customer_segment_id', $segmentId)
            ->orderByDesc('lifetime_value')
            ->limit($limit)
            ->get();
    }

    public function builder(): Builder
    {
        return CustomerSegment::query();
    }
}
