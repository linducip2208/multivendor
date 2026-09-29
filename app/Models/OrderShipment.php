<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'provider_id', 'courier', 'service', 'tracking_number', 'label_url', 'weight', 'cost', 'status', 'tracking_history', 'shipped_at', 'delivered_at'])]
class OrderShipment extends Model
{
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'cost' => 'decimal:2',
            'tracking_history' => 'array',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
