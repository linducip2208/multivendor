<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['vendor_id', 'name', 'slug', 'logo', 'banner', 'description', 'address', 'phone', 'email', 'bank_name', 'bank_account_number', 'bank_account_name', 'latitude', 'longitude', 'tin', 'commission_type', 'commission_value', 'vacation_mode', 'vacation_message', 'status', 'rejection_reason', 'city', 'province', 'postal_code', 'shipping_destination_id', 'rating_average', 'rating_count', 'product_count', 'sold_count', 'meta_title', 'meta_description'])]
class Shop extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'vacation_mode' => 'boolean',
            'commission_value' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'rating_average' => 'decimal:2',
            'rating_count' => 'integer',
            'product_count' => 'integer',
            'sold_count' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (empty($this->logo)) {
            return null;
        }

        return url('img/'.ltrim((string) $this->logo, '/'));
    }

    public function getBannerUrlAttribute(): ?string
    {
        if (empty($this->banner)) {
            return null;
        }

        return url('img/'.ltrim((string) $this->banner, '/'));
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function shippingMethods(): HasMany
    {
        return $this->hasMany(ShopShippingMethod::class);
    }

    public function withdrawRequests(): HasMany
    {
        return $this->hasMany(VendorWithdrawRequest::class);
    }

    // ── Pendalaman marketplace: KYC bertahap (aditif, tanpa kolom baru) ──

    /** Tahapan KYC: identitas → rekening → verifikasi. */
    public const KYC_STAGES = ['identity', 'bank', 'verified'];

    /**
     * Checklist KYC dari kolom existing (tin = identitas pajak, bank_* =
     * rekening, status active = terverifikasi). Tanpa log PII di mana pun.
     *
     * @return array{stage:string, stage_index:int, percent:int, steps:list<array{key:string, label:string, done:bool}>}
     */
    public function kycChecklist(): array
    {
        $identity = trim((string) ($this->tin ?? '')) !== '' || trim((string) ($this->phone ?? '')) !== '';
        $bank = trim((string) ($this->bank_account_number ?? '')) !== ''
            && trim((string) ($this->bank_name ?? '')) !== '';
        $verified = (string) $this->status === 'active' && $identity && $bank;

        $steps = [
            ['key' => 'identity', 'label' => 'Identitas & kontak', 'done' => $identity],
            ['key' => 'bank', 'label' => 'Rekening pencairan', 'done' => $bank],
            ['key' => 'verified', 'label' => 'Terverifikasi', 'done' => $verified],
        ];
        $done = count(array_filter($steps, fn (array $s): bool => $s['done']));

        return [
            'stage' => $verified ? 'verified' : ($bank ? 'bank' : 'identity'),
            'stage_index' => $verified ? 2 : ($bank ? 1 : 0),
            'percent' => (int) round($done / count($steps) * 100),
            'done' => $done,
            'steps' => $steps,
        ];
    }

    /** Nomor rekening tersensor untuk tampilan (4 digit terakhir saja). */
    public function maskedBankAccount(): string
    {
        $account = (string) ($this->bank_account_number ?? '');

        if ($account === '') {
            return '-';
        }

        return str_repeat('*', max(0, strlen($account) - 4)).substr($account, -4);
    }
}
