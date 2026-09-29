<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['warehouse_id', 'product_id', 'product_variant_id', 'on_hand', 'reserved', 'incoming', 'safety_stock', 'last_counted_at'])]
class ProductStock extends Model
{
    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'reserved' => 'integer',
            'incoming' => 'integer',
            'safety_stock' => 'integer',
            'last_counted_at' => 'datetime',
        ];
    }

    public function available(): int
    {
        return (int) $this->on_hand - (int) $this->reserved;
    }

    public function isLow(): bool
    {
        return $this->available() <= (int) $this->safety_stock;
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
