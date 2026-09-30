<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopTranslation extends Model
{
    protected $fillable = ['shop_id', 'locale', 'name', 'slug', 'description', 'meta_title', 'meta_description'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
