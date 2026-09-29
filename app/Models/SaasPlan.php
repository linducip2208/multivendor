<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'monthly_price', 'yearly_price', 'currency', 'max_products', 'max_vendors', 'max_staff', 'max_orders_per_month', 'max_storage_mb', 'pseo_page_quota', 'ai_request_quota', 'features', 'is_active', 'is_featured', 'sort_order'])]
class SaasPlan extends Model
{
    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'max_products' => 'integer',
            'max_vendors' => 'integer',
            'max_staff' => 'integer',
            'max_orders_per_month' => 'integer',
            'max_storage_mb' => 'integer',
            'pseo_page_quota' => 'integer',
            'ai_request_quota' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function priceFor(string $cycle): float
    {
        return $cycle === 'yearly' ? (float) $this->yearly_price : (float) $this->monthly_price;
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(SaasSubscription::class);
    }
}
