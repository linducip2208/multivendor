<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'product_id', 'product_variant_id', 'quantity',
    'price', 'tax', 'discount', 'sub_total', 'variant_detail',
    'is_reviewed', 'refund_status', 'refund_reason', 'refund_admin_note',
    'refund_requested_at', 'refund_decided_at', 'refund_processed_at',
    'refund_amount', 'refund_reference', 'fulfillment_status',
])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'sub_total' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'is_reviewed' => 'boolean',
            'refund_requested_at' => 'datetime',
            'refund_decided_at' => 'datetime',
            'refund_processed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
