<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['vendor_id', 'name', 'slug', 'logo', 'banner', 'description', 'address', 'phone', 'email', 'bank_name', 'bank_account_number', 'bank_account_name', 'latitude', 'longitude', 'tin', 'commission_type', 'commission_value', 'vacation_mode', 'vacation_message', 'status', 'rejection_reason'])]
class Shop extends Model
{
    protected function casts(): array
    {
        return [
            'vacation_mode' => 'boolean',
            'commission_value' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function shippingMethods(): HasMany
    {
        return $this->hasMany(ShopShippingMethod::class);
    }

    public function withdrawRequests(): HasMany
    {
        return $this->hasMany(VendorWithdrawRequest::class);
    }
}
