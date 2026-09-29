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

    /**
     * Tautan bagikan referral + pelacakan kanal (utm_source).
     * Tanpa library QR/barcode (belum terinstal) — bagikan via tautan +
     * tombol salin + poster unduhan, bukan gambar QR.
     */
    public function tautanBagikan(?string $baseUrl = null, string $kanal = 'umum'): string
    {
        $tautan = $this->referralLink($baseUrl);
        $kanal = trim(preg_replace('/[^a-z0-9_-]+/i', '', $kanal) ?? '');
        if ($kanal === '' || $kanal === 'umum') {
            return $tautan;
        }

        return $tautan.(str_contains($tautan, '?') ? '&' : '?').'utm_source='.urlencode($kanal);
    }

    /** Teks siap-bagikan (WA/medsos) berisi kode + tautan referral. */
    public function pesanBagikan(?string $baseUrl = null): string
    {
        return 'Belanja lewat tautanku '.$this->tautanBagikan($baseUrl).' — pakai kode referral '.(string) $this->code.' biar kami berdua dapat untung!';
    }

    /**
     * Poster referral SVG siap unduh (tanpa dependensi baru).
     * Berisi nama, kode besar, dan tautan — pengganti QR.
     */
    public function posterSvg(?string $baseUrl = null): string
    {
        $kode = htmlspecialchars((string) $this->code, ENT_QUOTES, 'UTF-8');
        $nama = htmlspecialchars(mb_substr((string) $this->name, 0, 40), ENT_QUOTES, 'UTF-8');
        $tautan = htmlspecialchars($this->tautanBagikan($baseUrl), ENT_QUOTES, 'UTF-8');
        $komisi = number_format((float) $this->commission_rate, 1, ',', '.');

        return '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="800" role="img" aria-label="Poster referral '.$kode.'">'
            .'<rect width="600" height="800" rx="24" fill="#0f766e"/>'
            .'<rect x="24" y="24" width="552" height="752" rx="16" fill="#ffffff"/>'
            .'<text x="300" y="110" text-anchor="middle" font-family="sans-serif" font-size="26" fill="#0f766e">'.$nama.'</text>'
            .'<text x="300" y="150" text-anchor="middle" font-family="sans-serif" font-size="18" fill="#64748b">Ajak teman, raih komisi '.$komisi.'%</text>'
            .'<rect x="90" y="200" width="420" height="150" rx="12" fill="#f0fdfa" stroke="#0f766e" stroke-width="2"/>'
            .'<text x="300" y="255" text-anchor="middle" font-family="monospace" font-size="52" font-weight="bold" fill="#0f766e">'.$kode.'</text>'
            .'<text x="300" y="300" text-anchor="middle" font-family="sans-serif" font-size="16" fill="#475569">Kode referral</text>'
            .'<text x="300" y="430" text-anchor="middle" font-family="sans-serif" font-size="17" fill="#0f172a">'.$tautan.'</text>'
            .'<text x="300" y="700" text-anchor="middle" font-family="sans-serif" font-size="15" fill="#94a3b8">Tunjukkan kode ini saat checkout</text>'
            .'</svg>';
    }

    /**
     * QR tidak tersedia: tidak ada paket QR/barcode terinstal
     * (composer show bersih). Gunakan tautan + tombol salin + poster SVG.
     */
    public function qrTersedia(): bool
    {
        return false;
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
