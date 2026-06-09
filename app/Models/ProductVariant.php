<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id', 'sku', 'variant', 'variant_attributes', 'price',
    'special_price', 'discount_type', 'discount_start', 'discount_end', 'stock'
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
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
