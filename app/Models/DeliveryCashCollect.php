<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['delivery_man_id', 'order_id', 'amount', 'collected', 'collected_at'])]
class DeliveryCashCollect extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'collected' => 'boolean', 'collected_at' => 'datetime'];
    }

    public function deliveryMan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_man_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function markCollected(): bool
    {
        if ($this->collected) {
            return false;
        }

        return $this->update(['collected' => true, 'collected_at' => now()]);
    }
}
