<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalProductOtp extends Model
{
    protected $table = 'digital_product_otps';

    protected $fillable = ['order_item_id', 'otp', 'verified', 'verified_at'];

    protected function casts(): array
    {
        return ['verified' => 'boolean', 'verified_at' => 'datetime'];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function verify(): void
    {
        $this->update(['verified' => true, 'verified_at' => now()]);
    }

    public static function generateForOrderItem(OrderItem $orderItem): static
    {
        return static::create([
            'order_item_id' => $orderItem->id,
            'otp' => strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)),
            'verified' => false,
        ]);
    }
}
