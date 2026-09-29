<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Models\Campaign;
use App\Models\Cart;
use App\Models\Category;
use App\Models\CustomerSegment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Support\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Campaign targeting and qualification rules.
 *
 * A rule set is a list of OR-groups; every group is a list of AND-conditions.
 * The engine answers two questions: may this customer receive the campaign, and
 * does this cart qualify for it. Both are evaluated against recorded facts only.
 */
final class CampaignRuleEngine
{
    public const RULE_TYPES = [
        'product_ids' => 'Produk',
        'category_ids' => 'Kategori',
        'brand_ids' => 'Brand',
        'shop_ids' => 'Toko',
        'segment_ids' => 'Segmen Pelanggan',
        'min_order_value' => 'Minimum nilai pesanan',
        'min_total_quantity' => 'Minimum jumlah produk',
        'max_total_quantity' => 'Maksimum jumlah produk',
        'day_of_week' => 'Hari dalam seminggu',
        'hour_of_day' => 'Jam',
        'min_usage_limit' => 'Kuota tersisa',
        'max_uses_per_user' => 'Maksimum pemakaian per pelanggan',
    ];

    /**
     * @return list<array{key: string, type: string, label: string, help: string, options?: array}>
     */
    public function catalogue(): array
    {
        return [
            ['key' => 'product_ids', 'type' => 'multiselect', 'label' => 'Produk', 'help' => 'Hanya produk ini yang memenuhi.', 'options' => $this->productOptions()],
            ['key' => 'category_ids', 'type' => 'multiselect', 'label' => 'Kategori', 'help' => 'Produk dari kategori ini memenuhi.', 'options' => $this->categoryOptions()],
            ['key' => 'brand_ids', 'type' => 'multiselect', 'label' => 'Brand', 'help' => 'Produk dari brand ini memenuhi.', 'options' => $this->brandOptions()],
            ['key' => 'shop_ids', 'type' => 'multiselect', 'label' => 'Toko', 'help' => 'Hanya toko ini yang memenuhi.', 'options' => $this->shopOptions()],
            ['key' => 'segment_ids', 'type' => 'multiselect', 'label' => 'Segmen pelanggan', 'help' => 'Pelanggan harus anggota segmen ini.', 'options' => $this->segmentOptions()],
            ['key' => 'min_order_value', 'type' => 'number', 'label' => 'Minimum nilai pesanan', 'help' => 'Total belanja minimal, dalam mata uang aktif.'],
            ['key' => 'min_total_quantity', 'type' => 'number', 'label' => 'Minimum jumlah produk', 'help' => 'Jumlah item dalam keranjang.'],
            ['key' => 'max_total_quantity', 'type' => 'number', 'label' => 'Maksimum jumlah produk', 'help' => 'Batas atas jumlah item dalam keranjang.'],
            ['key' => 'day_of_week', 'type' => 'select', 'label' => 'Hari', 'help' => 'Hanya aktif pada hari tertentu.', 'options' => [
                1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
            ]],
            ['key' => 'hour_of_day', 'type' => 'number', 'label' => 'Jam', 'help' => 'Hanya aktif pada jam tersebut (0-23).'],
            ['key' => 'min_usage_limit', 'type' => 'number', 'label' => 'Kuota tersisa', 'help' => 'Hanya ditampilkan bila kuota belum habis.'],
            ['key' => 'max_uses_per_user', 'type' => 'number', 'label' => 'Maks per pelanggan', 'help' => 'Batas pemakaian untuk satu pelanggan.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalise(?array $rules): array
    {
        if ($rules === null) {
            return [];
        }

        $normalised = [];

        foreach ($rules as $key => $value) {
            if (! array_key_exists($key, self::RULE_TYPES)) {
                continue;
            }

            $normalised[$key] = match (true) {
                in_array($key, ['product_ids', 'category_ids', 'brand_ids', 'shop_ids', 'segment_ids'], true) => array_values(array_unique(array_map('intval', (array) $value))),
                in_array($key, ['day_of_week'], true) => (int) $value,
                default => is_numeric($value) ? $value + 0 : $value,
            };
        }

        return array_filter($normalised, fn ($value): bool => $value !== [] && $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public function validate(array $rules): array
    {
        $errors = [];
        $clean = $this->normalise($rules);

        if (isset($clean['min_order_value']) && (float) $clean['min_order_value'] < 0) {
            $errors['rules'] = 'Minimum nilai pesanan tidak boleh negatif.';
        }

        if (isset($clean['day_of_week']) && ((int) $clean['day_of_week'] < 1 || (int) $clean['day_of_week'] > 7)) {
            $errors['rules'] = 'Hari harus berada antara 1 (Senin) dan 7 (Minggu).';
        }

        if (isset($clean['hour_of_day']) && ((int) $clean['hour_of_day'] < 0 || (int) $clean['hour_of_day'] > 23)) {
            $errors['rules'] = 'Jam harus berada antara 0 dan 23.';
        }

        if (isset($clean['min_total_quantity'], $clean['max_total_quantity'])
            && (int) $clean['min_total_quantity'] > (int) $clean['max_total_quantity']) {
            $errors['rules'] = 'Minimum jumlah produk tidak boleh lebih besar dari maksimum.';
        }

        return [$clean, $errors];
    }

    /**
     * Is the campaign live right now?
     */
    public function isLive(Campaign $campaign): bool
    {
        if ($campaign->status !== 'active') {
            return false;
        }

        if ($campaign->starts_at !== null && $campaign->starts_at->isFuture()) {
            return false;
        }

        if ($campaign->ends_at !== null && $campaign->ends_at->isPast()) {
            return false;
        }

        if (! $campaign->hasQuotaLeft()) {
            return false;
        }

        $rules = is_array($campaign->rules) ? $campaign->rules : [];

        if (isset($rules['min_usage_limit']) && $campaign->usage_limit !== null) {
            $remaining = (int) $campaign->usage_limit - (int) $campaign->used_count;
            if ($remaining < (int) $rules['min_usage_limit']) {
                return false;
            }
        }

        if (isset($rules['day_of_week']) && (int) now()->dayOfWeekIso !== (int) $rules['day_of_week']) {
            return false;
        }

        if (isset($rules['hour_of_day']) && (int) now()->hour !== (int) $rules['hour_of_day']) {
            return false;
        }

        return true;
    }

    /**
     * Does a cart qualify? Products are read from the cart table.
     *
     * @param  array<string, mixed>  $rules
     * @return array{eligible: bool, reasons: list<string>}
     */
    public function evaluateCart(array $rules, ?int $customerId): array
    {
        $rules = $this->normalise($rules);
        $reasons = [];

        $cart = $customerId === null
            ? collect()
            : Cart::query()->where('customer_id', $customerId)->get();

        $items = [];
        $quantity = 0;
        $value = 0.0;

        foreach ($cart as $line) {
            $productId = (int) ($line->product_id ?? 0);
            $qty = max(1, (int) ($line->quantity ?? 1));
            $product = $productId > 0 ? Product::query()->with('category:id,name')->find($productId) : null;

            $items[] = [
                'product_id' => $productId,
                'quantity' => $qty,
                'category_id' => $product?->category_id,
                'brand_id' => $product?->brand_id,
                'shop_id' => $product?->shop_id,
                'price' => (float) ($product?->price ?? 0),
            ];

            $quantity += $qty;
            $value += $qty * (float) ($product?->price ?? 0);
        }

        if ($items !== [] && isset($rules['product_ids']) && $rules['product_ids'] !== []) {
            $matched = array_intersect($rules['product_ids'], array_column($items, 'product_id'));

            if ($matched === []) {
                $reasons[] = 'Tidak ada produk dalam keranjang yang termasuk daftar produk kampanye.';
            } else {
                $reasons[] = count($matched).' produk memenuhi daftar produk.';
            }
        }

        foreach ([
            'category_ids' => 'kategori',
            'brand_ids' => 'brand',
            'shop_ids' => 'toko',
        ] as $key => $noun) {
            if (! isset($rules[$key]) || $rules[$key] === []) {
                continue;
            }

            $matched = array_intersect($rules[$key], array_filter(array_column($items, $key === 'category_ids' ? 'category_id' : ($key === 'brand_ids' ? 'brand_id' : 'shop_id'))));

            if ($matched === []) {
                $reasons[] = 'Keranjang tidak memuat '.$noun.' yang diizinkan.';
            } else {
                $reasons[] = count($matched).' '.$noun.' memenuhi syarat.';
            }
        }

        if (isset($rules['min_order_value']) && $value < (float) $rules['min_order_value']) {
            $reasons[] = 'Minimum belanja '.Currency::format((float) $rules['min_order_value']).' belum tercapai.';
        }

        if (isset($rules['min_total_quantity']) && $quantity < (int) $rules['min_total_quantity']) {
            $reasons[] = 'Keranjang harus berisi minimal '.(int) $rules['min_total_quantity'].' produk.';
        }

        if (isset($rules['max_total_quantity']) && $quantity > (int) $rules['max_total_quantity']) {
            $reasons[] = 'Keranjang maksimal '.(int) $rules['max_total_quantity'].' produk.';
        }

        if ($customerId !== null && isset($rules['segment_ids']) && $rules['segment_ids'] !== []) {
            $member = $this->isInSegment($customerId, $rules['segment_ids']);

            $reasons[] = $member
                ? 'Pelanggan termasuk segmen target.'
                : 'Pelanggan bukan anggota segmen target.';
        }

        if ($customerId !== null && isset($rules['max_uses_per_user'])) {
            $used = (int) $this->usesByCustomer($customerId);

            if ($used >= (int) $rules['max_uses_per_user']) {
                $reasons[] = 'Batas pemakaian_campaign untuk pelanggan ini sudah tercapai.';
            }
        }

        $blocking = array_filter($reasons, fn (string $reason): bool => str_starts_with($reason, 'Tidak') || str_starts_with($reason, 'Keranjang') || str_starts_with($reason, 'Minimum') || str_starts_with($reason, 'Pelanggan bukan') || str_starts_with($reason, 'Batas'));

        return [
            'eligible' => $blocking === [],
            'reasons' => $reasons,
            'value' => $value,
            'quantity' => $quantity,
        ];
    }

    /**
     * @param  list<int>  $segmentIds
     */
    public function isInSegment(int $customerId, array $segmentIds): bool
    {
        if ($segmentIds === []) {
            return false;
        }

        try {
            return DB::table('customer_segment_members')
                ->where('customer_id', $customerId)
                ->whereIn('customer_segment_id', $segmentIds)
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public function usesByCustomer(int $customerId): int
    {
        try {
            return (int) DB::table('orders')
                ->where('customer_id', $customerId)
                ->whereNotNull('coupon_code')
                ->whereNotIn('order_status', ['canceled', 'failed'])
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return array<string, int>
     */
    private function productOptions(): array
    {
        try {
            return Product::query()->orderBy('name')->limit(300)->pluck('name', 'id')
                ->map(fn (string $name): string => Str::limit($name, 60))->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, string>
     */
    private function categoryOptions(): array
    {
        try {
            return Category::query()->orderBy('name')->pluck('name', 'id')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, string>
     */
    private function brandOptions(): array
    {
        try {
            return \App\Models\Brand::query()->orderBy('name')->pluck('name', 'id')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, string>
     */
    private function shopOptions(): array
    {
        try {
            return Shop::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, string>
     */
    private function segmentOptions(): array
    {
        try {
            return CustomerSegment::query()->orderBy('name')->pluck('name', 'id')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Human-readable summary of a rule set for list screens.
     *
     * @param  array<string, mixed>|null  $rules
     * @return list<array{label: string, value: string}>
     */
    public function describe(?array $rules): array
    {
        $rules = $this->normalise($rules);
        $out = [];

        foreach (self::RULE_TYPES as $key => $label) {
            if (! isset($rules[$key]) || $rules[$key] === []) {
                continue;
            }

            $value = $rules[$key];
            $out[] = [
                'label' => $label,
                'value' => is_array($value) ? implode(', ', array_map('strval', array_slice($value, 0, 6))).(count($value) > 6 ? ' +'.(count($value) - 6) : '') : (string) $value,
            ];
        }

        return $out;
    }

    /**
     * @return Builder<Campaign>
     */
    public function liveCampaigns(): Builder
    {
        return Campaign::query()
            ->where('status', 'active')
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /**
     * @return list<int>
     */
    public function customerIdsFor(array $rules, int $limit = 2000): array
    {
        $rules = $this->normalise($rules);
        $segmentIds = $rules['segment_ids'] ?? [];

        $query = User::query()->where('role', 'customer')->limit($limit);

        if ($segmentIds !== []) {
            $query->whereIn('id', function ($sub) use ($segmentIds): void {
                $sub->select('customer_id')->from('customer_segment_members')->whereIn('customer_segment_id', $segmentIds);
            });
        }

        return $query->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }
}
