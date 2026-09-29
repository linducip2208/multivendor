<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id', 'sku', 'variant', 'variant_attributes', 'price',
    'special_price', 'discount_type', 'discount_start', 'discount_end', 'stock',
    'low_stock_threshold',
])]
class ProductVariant extends Model
{
    protected function casts(): array
    {
        return [
            'variant_attributes' => 'json',
            'price' => 'decimal:2',
            'special_price' => 'decimal:2',
            'discount_start' => 'datetime',
            'discount_end' => 'datetime',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getEffectivePrice(): float
    {
        if ($this->special_price && (!$this->discount_start || $this->discount_start <= now()) && (!$this->discount_end || $this->discount_end >= now())) {
            return (float) $this->special_price;
        }

        return (float) $this->price;
    }
}
