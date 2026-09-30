<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandTranslation extends Model
{
    protected $fillable = ['brand_id', 'locale', 'name', 'slug', 'description', 'meta_title', 'meta_description'];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
