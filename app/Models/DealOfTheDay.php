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
}
