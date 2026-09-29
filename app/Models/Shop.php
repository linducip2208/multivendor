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
}
