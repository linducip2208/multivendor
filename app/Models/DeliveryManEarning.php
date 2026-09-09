<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['delivery_man_id', 'order_id', 'amount', 'description'])]
class DeliveryManEarning extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function deliveryMan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_man_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
