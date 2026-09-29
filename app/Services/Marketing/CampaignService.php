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

    /**
     * Ringkasan audiens segmen sebuah kampanye (nama segmen dibaca dari
     * customer_segments; kampanye tanpa segmen menargetkan semua).
     *
     * @return array{segment_ids: list<int>, segments: list<array{id: int, name: string}>, terbuka: bool}
     */
    public function audienceSummary(Campaign $campaign): array
    {
        $ids = $campaign->segmentIds();

        $segments = [];
        if ($ids !== []) {
            try {
                $segments = \App\Models\CustomerSegment::query()
                    ->whereIn('id', $ids)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn ($s): array => ['id' => (int) $s->id, 'name' => (string) $s->name])
                    ->all();
            } catch (\Throwable) {
                $segments = [];
            }
        }

        return [
            'segment_ids' => $ids,
            'segments' => $segments,
            'terbuka' => $ids === [],
        ];
    }

    /**
     * Ringkasan retensi (abandoned, ultah, banner, flash) untuk panel admin.
     *
     * @return array<string, mixed>
     */
    public function retentionSnapshot(): array
    {
        return app(RetentionService::class)->overview();
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

    /* ------------------------------------------------------------------ */
    /* Cashback + stack tebus (perdalaman aditif, tanpa ubah method lama)   */
    /* ------------------------------------------------------------------ */

    /**
     * Nominal cashback sebuah kampanye untuk nilai pesanan tertentu.
     *
     * Kolom terverifikasi ke migrasi 2026_09_28_000002 (discount_value,
     * discount_type, starts_at, ends_at): persen dihitung dari nilai pesanan,
     * nominal (flat) dipakai apa adanya; selalu dibatasi nilai pesanan dan
     * nol bila kampanye bukan cashback / di luar periode / nonaktif.
     */
    public function hitungCashback(Campaign $campaign, float $nilaiPesanan): float
    {
        if ($campaign->type !== 'cashback' || $nilaiPesanan <= 0) {
            return 0.0;
        }

        if ($campaign->status !== 'active') {
            return 0.0;
        }

        if ($campaign->starts_at !== null && $campaign->starts_at->isFuture()) {
            return 0.0;
        }

        if ($campaign->ends_at !== null && $campaign->ends_at->isPast()) {
            return 0.0;
        }

        $nominal = ((string) $campaign->discount_type === 'percentage')
            ? $nilaiPesanan * ((float) $campaign->discount_value / 100)
            : (float) $campaign->discount_value;

        return round(max(0.0, min($nominal, $nilaiPesanan)), 2);
    }

    /**
     * Kampanye cashback yang sedang live (jenis + periode + kuota).
     *
     * @return list<array{id: int, name: string, discount_value: float, discount_type: string}>
     */
    public function daftarCashbackAktif(int $limit = 20): array
    {
        try {
            return Campaign::query()->where('type', 'cashback')
                ->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->orderByDesc('id')
                ->limit(max(1, min(100, $limit)))
                ->get()
                ->filter(fn (Campaign $c): bool => $c->hasQuotaLeft())
                ->map(fn (Campaign $c): array => [
                    'id' => (int) $c->id,
                    'name' => (string) $c->name,
                    'discount_value' => (float) $c->discount_value,
                    'discount_type' => (string) $c->discount_type,
                ])->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Berikan cashback ke dompet (default) atau poin loyalitas.
     *
     * Idempoten per (kampanye, order): kunci `cashback-{campaign}-order-{order}`
     * dipakai sebagai wallet reference_key dan sebagai pasangan
     * loyalty (reference_type=cashback, reference_id=order). Batas periode,
     * usage_limit kampanye, dan per_user_limit dihormati.
     *
     * @return array{dikredit: bool, sudah_ada: bool, nominal: float, poin: int, tujuan: string, status: string}
     */
    public function berikanCashback(
        Campaign $campaign,
        \App\Models\User $customer,
        int $orderId,
        float $nilaiPesanan,
        string $tujuan = 'dompet',
    ): array {
        $tujuan = $tujuan === 'poin' ? 'poin' : 'dompet';
        $kunci = 'cashback-'.(int) $campaign->id.'-order-'.$orderId;

        if ($campaign->type !== 'cashback') {
            return ['dikredit' => false, 'sudah_ada' => false, 'nominal' => 0.0, 'poin' => 0, 'tujuan' => $tujuan, 'status' => 'bukan kampanye cashback'];
        }

        $nominal = $this->hitungCashback($campaign, $nilaiPesanan);
        if ($nominal <= 0) {
            return ['dikredit' => false, 'sudah_ada' => false, 'nominal' => 0.0, 'poin' => 0, 'tujuan' => $tujuan, 'status' => 'di luar periode / tidak memenuhi syarat'];
        }

        if ($campaign->usage_limit !== null && (int) $campaign->used_count >= (int) $campaign->usage_limit) {
            return ['dikredit' => false, 'sudah_ada' => false, 'nominal' => $nominal, 'poin' => 0, 'tujuan' => $tujuan, 'status' => 'kuota kampanye habis'];
        }

        $customerId = (int) $customer->getKey();

        return DB::transaction(function () use ($campaign, $customer, $customerId, $orderId, $nominal, $tujuan, $kunci): array {
            $kampanye = Campaign::query()->lockForUpdate()->find((int) $campaign->id);
            if (! $kampanye instanceof Campaign) {
                return ['dikredit' => false, 'sudah_ada' => false, 'nominal' => $nominal, 'poin' => 0, 'tujuan' => $tujuan, 'status' => 'kampanye tidak ditemukan'];
            }

            // Replay idempoten: kunci sudah pernah dikredit sebelumnya.
            if ($tujuan === 'dompet') {
                $dompet = \App\Models\Wallet::firstOrCreate(['user_id' => $customerId], ['balance' => 0, 'pending_balance' => 0]);
                $ada = $dompet->transactions()->where('reference_key', $kunci)->first();
                if ($ada) {
                    return ['dikredit' => false, 'sudah_ada' => true, 'nominal' => (float) $ada->amount, 'poin' => 0, 'tujuan' => $tujuan, 'status' => 'sudah dikredit sebelumnya'];
                }
            } else {
                $ada = \App\Models\LoyaltyTransaction::query()
                    ->where('customer_id', $customerId)
                    ->where('reference_type', 'cashback')
                    ->where('reference_id', $orderId)
                    ->where('description', 'like', '%#'.(int) $kampanye->id.'%')
                    ->first();
                if ($ada) {
                    return ['dikredit' => false, 'sudah_ada' => true, 'nominal' => 0.0, 'poin' => (int) $ada->points, 'tujuan' => $tujuan, 'status' => 'sudah dikredit sebelumnya'];
                }
            }

            // Batas per pelanggan (dihitung dari riwayat nyata kedua dompet).
            if ($kampanye->per_user_limit !== null) {
                $terpakai = $this->hitungPakaiCashbackPelanggan($kampanye, $customerId);
                if ($terpakai >= (int) $kampanye->per_user_limit) {
                    return ['dikredit' => false, 'sudah_ada' => false, 'nominal' => $nominal, 'poin' => 0, 'tujuan' => $tujuan, 'status' => 'batas per pelanggan tercapai'];
                }
            }

            if ($tujuan === 'dompet') {
                $dompet = \App\Models\Wallet::firstOrCreate(['user_id' => $customerId], ['balance' => 0, 'pending_balance' => 0]);
                $dompet->credit($nominal, 'Cashback kampanye #'.(int) $kampanye->id.' order #'.$orderId, 'cashback', (int) $kampanye->id, $kunci);
                $kampanye->increment('used_count');

                return ['dikredit' => true, 'sudah_ada' => false, 'nominal' => $nominal, 'poin' => 0, 'tujuan' => $tujuan, 'status' => 'dikredit ke dompet'];
            }

            $poin = max(1, (int) floor($nominal));
            $hasil = (new \App\Models\LoyaltyPoint)->kreditCashback($customer, $poin, $orderId, 'Cashback kampanye #'.(int) $kampanye->id.' order #'.$orderId);
            if (! $hasil['dikredit'] && ! $hasil['sudah_ada']) {
                return ['dikredit' => false, 'sudah_ada' => false, 'nominal' => $nominal, 'poin' => 0, 'tujuan' => $tujuan, 'status' => $hasil['status']];
            }
            if ($hasil['dikredit']) {
                $kampanye->increment('used_count');
            }

            return ['dikredit' => $hasil['dikredit'], 'sudah_ada' => $hasil['sudah_ada'], 'nominal' => $nominal, 'poin' => $hasil['dikredit'] ? $poin : (int) ($hasil['poin'] ?? 0), 'tujuan' => $tujuan, 'status' => $hasil['status']];
        });
    }

    /**
     * Simulasi gabungan tebus poin + kupon dalam satu checkout.
     *
     * Aturan stack aman: kupon dulu (dibatasi subtotal), lalu poin (dibatasi
     * sisa bayar), total bayar tak pernah minus.
     *
     * @return array{subtotal: float, diskon_kupon: float, poin_diminta: int, poin_dipakai: int, nilai_poin: float, total_bayar: float, hemat: float}
     */
    public function simulasiStackCheckout(
        float $subtotal,
        ?\App\Models\Coupon $kupon,
        int $poinDiminta,
        float $nilaiPerPoin = 1.0,
    ): array {
        $subtotal = max(0.0, $subtotal);
        $nilaiPerPoin = $nilaiPerPoin > 0 ? $nilaiPerPoin : 1.0;
        $poinDiminta = max(0, $poinDiminta);

        $diskonKupon = $kupon instanceof \App\Models\Coupon
            ? max(0.0, min($kupon->calculateDiscount($subtotal), $subtotal))
            : 0.0;

        $sisa = max(0.0, $subtotal - $diskonKupon);
        $nilaiPoin = min($poinDiminta * $nilaiPerPoin, $sisa);
        $poinDipakai = (int) floor($nilaiPoin / $nilaiPerPoin);
        $nilaiPoin = round($poinDipakai * $nilaiPerPoin, 2);
        $totalBayar = round(max(0.0, $sisa - $nilaiPoin), 2);

        return [
            'subtotal' => round($subtotal, 2),
            'diskon_kupon' => round($diskonKupon, 2),
            'poin_diminta' => $poinDiminta,
            'poin_dipakai' => $poinDipakai,
            'nilai_poin' => $nilaiPoin,
            'total_bayar' => $totalBayar,
            'hemat' => round($subtotal - $totalBayar, 2),
        ];
    }

    /**
     * Jumlah cashback kampanye yang sudah dipakai satu pelanggan
     * (dompet reference_key + loyalty reference earn).
     */
    public function hitungPakaiCashbackPelanggan(Campaign $campaign, int $customerId): int
    {
        $pakai = 0;

        try {
            $dompetId = \App\Models\Wallet::query()->where('user_id', $customerId)->value('id');
            if ($dompetId) {
                $pakai += (int) \App\Models\WalletTransaction::query()
                    ->where('wallet_id', $dompetId)
                    ->where('reference_type', 'cashback')
                    ->where('reference_id', (int) $campaign->id)
                    ->count();
            }
        } catch (\Throwable) {
        }

        try {
            $pakai += (int) \App\Models\LoyaltyTransaction::query()
                ->where('customer_id', $customerId)
                ->where('type', 'earn')
                ->where('reference_type', 'cashback')
                ->where('description', 'like', '%#'.(int) $campaign->id.'%')
                ->count();
        } catch (\Throwable) {
        }

        return $pakai;
    }
}
