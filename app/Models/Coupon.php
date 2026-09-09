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
