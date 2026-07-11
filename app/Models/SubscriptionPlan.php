<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'price', 'billing_period',
        'max_products', 'max_images_per_product', 'commission_rate',
        'can_chat', 'can_export', 'can_bulk_import', 'can_pos',
        'can_barcode', 'featured_shop', 'features', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'is_active' => 'boolean',
            'can_chat' => 'boolean',
            'can_export' => 'boolean',
            'can_bulk_import' => 'boolean',
            'can_pos' => 'boolean',
            'can_barcode' => 'boolean',
            'featured_shop' => 'boolean',
            'features' => 'json',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(VendorSubscription::class);
    }
}
