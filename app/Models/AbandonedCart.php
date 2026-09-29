<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'email', 'session_id', 'item_count', 'amount', 'items', 'reminder_count', 'last_reminder_at', 'recovered_at', 'recovered_order_id'])]
class AbandonedCart extends Model
{
    protected function casts(): array
    {
        return [
            'item_count' => 'integer',
            'amount' => 'decimal:2',
            'items' => 'array',
            'reminder_count' => 'integer',
            'last_reminder_at' => 'datetime',
            'recovered_at' => 'datetime',
        ];
    }

    public function scopeAbandoned(Builder $query): Builder
    {
        return $query->whereNull('recovered_at');
    }

    public function isRecovered(): bool
    {
        return $this->recovered_at !== null;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function recoveredOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'recovered_order_id');
    }
}
