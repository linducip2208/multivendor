<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'logo', 'description', 'meta_title', 'meta_description', 'is_featured', 'status'])]
class Brand extends Model
{
    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (empty($this->logo)) {
            return null;
        }

        return url('img/'.ltrim((string) $this->logo, '/'));
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
