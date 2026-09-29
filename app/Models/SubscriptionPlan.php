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
        'max_staff', 'max_storage_mb', 'max_monthly_transactions', 'max_shop_limit',
        'commission_value', 'commission_type', 'commission_tier',
        'grace_days', 'trial_days', 'grace_allowed',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_value' => 'decimal:2',
            'max_monthly_transactions' => 'integer',
            'max_shop_limit' => 'integer',
            'max_staff' => 'integer',
            'max_storage_mb' => 'integer',
            'grace_days' => 'integer',
            'trial_days' => 'integer',
            'grace_allowed' => 'boolean',
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
