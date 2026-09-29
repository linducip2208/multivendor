<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'code', 'name', 'email', 'status', 'commission_rate', 'total_commission', 'total_orders', 'total_revenue', 'approved_at'])]
class Affiliate extends Model
{
    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'total_commission' => 'decimal:2',
            'total_revenue' => 'decimal:2',
            'total_orders' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }

    /** Cari afiliasi dari kode referral (tak peduli huruf besar/kecil). */
    public static function findByCode(string $code): ?static
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        return static::whereRaw('LOWER(code) = ?', [mb_strtolower($code)])->first();
    }

    /** Tautan dasbor afiliasi siap bagikan (`?ref=KODE`). */
    public function referralLink(?string $baseUrl = null): string
    {
        try {
            $base = $baseUrl ?? route('products.index');
        } catch (\Throwable) {
            $base = $baseUrl ?? url('/products');
        }

        return rtrim($base, '?&').(str_contains($base, '?') ? '&' : '?').'ref='.urlencode((string) $this->code);
    }

    /** Catat satu klik ke tautan afiliasi ini. */
    public function recordClick(array $attrs = []): AffiliateClick
    {
        return AffiliateClick::track($this, $attrs);
    }

    /**
     * Atribusikan order ke afiliasi via kolom khusus `orders.referral_code`
     * (fallback `coupon_code` untuk order lama sebelum migrasi
     * 2026_09_30_030000). Idempoten per order.
     */
    public function attributeOrder(Order $order): bool
    {
        $code = $order->referral_code ?? null;
        if (! is_string($code) || trim($code) === '') {
            $code = $order->coupon_code ?? null;
        }
        $orderCode = mb_strtolower(trim((string) $code));
        if ($orderCode === '' || $orderCode !== mb_strtolower(trim((string) $this->code))) {
            return false;
        }
        if (! $this->isActive()) {
            return false;
        }
        $already = AffiliateClick::where('affiliate_id', $this->id)
            ->where('converted_order_id', $order->id)->exists();
        if ($already) {
            return false;
        }

        $total = (float) ($order->total ?? 0);
        $commission = round($total * ((float) $this->commission_rate / 100), 2);

        $this->forceFill([
            'total_orders' => (int) $this->total_orders + 1,
            'total_revenue' => (float) $this->total_revenue + $total,
            'total_commission' => (float) $this->total_commission + $commission,
        ])->save();

        $click = AffiliateClick::where('affiliate_id', $this->id)
            ->whereNull('converted_order_id')
            ->latest('id')->first();
        if ($click) {
            $click->markConverted($order);
        } else {
            AffiliateClick::create([
                'affiliate_id' => $this->id,
                'customer_id' => $order->customer_id,
                'landing_path' => null,
                'converted_order_id' => $order->id,
                'converted_at' => now(),
            ]);
        }

        return true;
    }

    /**
     * Bayarkan komisi ke dompet pemilik afiliasi (wallet existing).
     * Idempoten via reference_key bila diberikan.
     */
    public function payoutToWallet(float $amount, ?string $referenceKey = null): ?\App\Models\WalletTransaction
    {
        if ($amount <= 0 || ! $this->user_id) {
            return null;
        }
        $wallet = \App\Models\Wallet::firstOrCreate(['user_id' => $this->user_id], ['balance' => 0, 'pending_balance' => 0]);

        return $wallet->credit(
            $amount,
            'Komisi afiliasi '.$this->code,
            'affiliate_payout',
            (int) $this->id,
            $referenceKey ?? ('affiliate-payout-'.$this->id.'-'.now()->format('YmdHis'))
        );
    }

    /** Papan peringkat afiliasi berdasar omzet (read-only). */
    public static function leaderboard(int $limit = 10): array
    {
        try {
            return static::query()->where('status', 'active')
                ->orderByDesc('total_revenue')->limit(max(1, min(50, $limit)))->get()
                ->map(fn (Affiliate $a) => [
                    'id' => (int) $a->id,
                    'name' => (string) $a->name,
                    'code' => (string) $a->code,
                    'total_orders' => (int) $a->total_orders,
                    'total_revenue' => (float) $a->total_revenue,
                    'total_commission' => (float) $a->total_commission,
                    'clicks' => (int) $a->clicks()->count(),
                ])->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
