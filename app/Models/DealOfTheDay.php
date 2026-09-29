<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealOfTheDay extends Model
{
    protected $table = 'deals_of_the_day';
    protected $fillable = ['product_id', 'discount_type', 'discount_value', 'date'];

    protected function casts(): array
    {
        return ['date' => 'date', 'discount_value' => 'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Effective discount percentage for a single deal row. Percentage rows
     * contribute discount_value directly; flat rows convert vs product price.
     */
    public static function effectiveExpression(string $priceColumn = 'products.price'): string
    {
        return '(case when deals_of_the_day.discount_type = '
            ."'flat' then deals_of_the_day.discount_value * 100.0 / nullif({$priceColumn}, 0) "
            .'else deals_of_the_day.discount_value end)';
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<DealOfTheDay> $query
     */
    public function scopeOrderByEffectiveDiscount($query, string $direction = 'desc'): void
    {
        $query->orderByRaw(static::effectiveExpression().' '.($direction === 'asc' ? 'asc' : 'desc'));
    }

    public function getEffectiveDiscountPercentageAttribute(): float
    {
        $value = (float) ($this->discount_value ?? 0);

        if ($value <= 0) {
            return 0.0;
        }

        if (($this->discount_type ?? null) === 'percentage') {
            return min(100.0, $value);
        }

        $price = (float) ($this->relationLoaded('product') ? ($this->product?->price ?? 0) : 0);

        if ($price <= 0) {
            return 0.0;
        }

        return min(100.0, $value / $price * 100);
    }
}
