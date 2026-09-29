<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'name', 'slug', 'icon', 'image', 'description', 'banner_image', 'meta_title', 'meta_description', 'is_featured', 'sort_order', 'status'])]
class Category extends Model
{
    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        return url('img/'.ltrim((string) $this->image, '/'));
    }

    public function getBannerUrlAttribute(): ?string
    {
        if (empty($this->banner_image)) {
            return null;
        }

        return url('img/'.ltrim((string) $this->banner_image, '/'));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
