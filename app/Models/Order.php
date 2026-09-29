<?php

declare(strict_types=1);

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
    'delivered_at', 'canceled_at', 'payment_group_id', 'stock_released_at',
    'parent_order_id', 'source', 'fulfillment_status', 'warehouse_id',
    'packed_at', 'completed_at', 'returned_at', 'refunded_at', 'return_reason',
    'refunded_amount', 'currency', 'idempotency_key', 'pos_shift_id', 'pos_register_id',
    'reconciled_at',
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
            'refunded_amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'processing_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'canceled_at' => 'datetime',
            'stock_released_at' => 'datetime',
            'packed_at' => 'datetime',
            'completed_at' => 'datetime',
            'returned_at' => 'datetime',
            'refunded_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function paymentGroup(): BelongsTo
    {
        return $this->belongsTo(PaymentGroup::class);
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_order_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_order_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(OrderShipment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function posShift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'pos_shift_id');
    }

    public static function generateOrderNumber(): string
    {
        $prefix = \App\Models\SystemSetting::get('order_prefix', 'ORD');
        do {
            $number = "{$prefix}-".now()->format('Ymd').'-'.strtoupper(\Illuminate\Support\Str::random(10));
        } while (static::where('order_number', $number)->exists());
        return $number;
    }
}
