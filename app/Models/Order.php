<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_number', 'customer_id', 'shop_id', 'coupon_code', 'coupon_discount',
    'sub_total', 'tax', 'shipping_cost', 'discount', 'total',
    'shipping_method', 'shipping_service', 'shipping_tracking_id',
    'delivery_verification_code', 'delivery_man_id', 'shipping_address',
    'billing_address', 'payment_method', 'payment_status', 'order_status',
    'note', 'cancel_reason', 'confirmed_at', 'processing_at', 'shipped_at',
    'delivered_at', 'canceled_at'
])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'shipping_address' => 'json',
            'billing_address' => 'json',
            'sub_total' => 'decimal:2',
            'tax' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'discount' => 'decimal:2',
            'coupon_discount' => 'decimal:2',
            'total' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'processing_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function transaction(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function deliveryMan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_man_id');
    }

    public static function generateOrderNumber(): string
    {
        $prefix = \App\Models\SystemSetting::get('order_prefix', 'ORD');
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(substr(uniqid(), -4));
        return "{$prefix}-{$timestamp}-{$random}";
    }
}
