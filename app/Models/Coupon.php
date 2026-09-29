<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'shop_id', 'code', 'title', 'coupon_type', 'discount_value', 'min_purchase',
    'max_discount', 'start_date', 'end_date', 'usage_limit',
    'usage_per_customer', 'usage_count', 'status'
])]
class Coupon extends Model
{
    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'min_purchase' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'status' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_product');
    }

    public function shop(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'coupon_category');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function isValid(?int $customerId = null): bool
    {
        if (!$this->status) return false;
        if ($this->start_date && now()->lt($this->start_date)) return false;
        if ($this->end_date && now()->gt($this->end_date)) return false;
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) return false;
        if ($customerId && $this->usage_per_customer && $this->usages()->where('customer_id', $customerId)->count() >= $this->usage_per_customer) return false;
        return true;
    }

    /**
     * Buat kode personal unik (mis. "HADIAH-4F8K2N") yang dijamin belum
     * dipakai kupon lain. Huruf besar alfanumerik agar mudah dibaca pelanggan.
     */
    public static function buatKodePersonal(string $awalan = 'KUPON', int $panjangAcak = 6): string
    {
        $awalan = strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '', $awalan) ?? ''));
        $awalan = $awalan === '' ? 'KUPON' : mb_substr($awalan, 0, 12);
        $panjangAcak = max(4, min(10, $panjangAcak));

        do {
            $kode = $awalan.'-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random($panjangAcak));
            $kode = preg_replace('/[^A-Z0-9-]/', '', $kode) ?? $awalan;
        } while (static::query()->where('code', $kode)->exists());

        return $kode;
    }

    /**
     * Sisa kuota pakai untuk satu pelanggan (null bila tak dibatasi).
     */
    public function sisaKuotaUntuk(?int $customerId): ?int
    {
        if ($customerId === null || ! $this->usage_per_customer) {
            return null;
        }

        $terpakai = $this->usages()->where('customer_id', $customerId)->count();

        return max(0, (int) $this->usage_per_customer - $terpakai);
    }

    public function bolehDipakaiOleh(?int $customerId): bool
    {
        if (! $this->isValid($customerId)) {
            return false;
        }

        if ($customerId !== null && $this->sisaKuotaUntuk($customerId) === 0) {
            return false;
        }

        return true;
    }

    public function memenuhiMinimumBelanja(float $total): bool
    {
        return $total >= (float) $this->min_purchase;
    }

    /**
     * Minimum belanja dihitung dari subtotal kategori yang dicakup kupon.
     * Bila kupon tidak terikat kategori, $subtotalKategori diabaikan dan
     * yang dipakai total belanja keseluruhan.
     */
    public function memenuhiMinimumKategori(float $subtotalKategori, float $totalBelanja = 0.0): bool
    {
        if ($this->relationLoaded('categories') ? $this->categories->isEmpty() : $this->categories()->count() === 0) {
            return $this->memenuhiMinimumBelanja($totalBelanja > 0 ? $totalBelanja : $subtotalKategori);
        }

        return $subtotalKategori >= (float) $this->min_purchase;
    }

    /**
     * Laporan pemakaian: dihitung ulang dari tabel coupon_usages sehingga
     * tidak pernah melenceng dari catatan transaksi yang sebenarnya.
     *
     * @return array{total_pakai: int, total_diskon: float, pelanggan_unik: int, sisa_kuota: int|null}
     */
    public function ringkasanPemakaian(): array
    {
        try {
            $totalPakai = (int) $this->usages()->count();
            $totalDiskon = (float) $this->usages()->sum('discount_amount');
            $pelangganUnik = (int) $this->usages()->distinct()->count('customer_id');
        } catch (\Throwable) {
            $totalPakai = (int) $this->usage_count;
            $totalDiskon = 0.0;
            $pelangganUnik = 0;
        }

        return [
            'total_pakai' => $totalPakai,
            'total_diskon' => $totalDiskon,
            'pelanggan_unik' => $pelangganUnik,
            'sisa_kuota' => $this->usage_limit === null ? null : max(0, (int) $this->usage_limit - $totalPakai),
        ];
    }

    public function calculateDiscount(float $orderTotal): float
    {
        if ($orderTotal < $this->min_purchase) return 0;

        if ($this->coupon_type === 'free_shipping') return 0;
        $discount = $this->coupon_type === 'percentage'
            ? $orderTotal * ($this->discount_value / 100)
            : $this->discount_value;

        if ($this->max_discount && $discount > $this->max_discount) {
            $discount = (float) $this->max_discount;
        }

        return min($discount, $orderTotal);
    }
}
