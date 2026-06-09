<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['flash_deal_id', 'product_id', 'discount_type', 'discount_value'])]
class FlashDealProduct extends Model
{
    protected function casts(): array
    {
        return ['discount_value' => 'decimal:2'];
    }

    public function flashDeal(): BelongsTo
    {
        return $this->belongsTo(FlashDeal::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
