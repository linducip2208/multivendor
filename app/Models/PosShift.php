<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['pos_register_id', 'cashier_id', 'status', 'opening_cash', 'expected_cash', 'counted_cash', 'variance', 'sales_total', 'transaction_count', 'note', 'opened_at', 'closed_at'])]
class PosShift extends Model
{
    protected function casts(): array
    {
        return [
            'opening_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'variance' => 'decimal:2',
            'sales_total' => 'decimal:2',
            'transaction_count' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function calculatedVariance(): float
    {
        return (float) $this->counted_cash - (float) $this->expected_cash;
    }

    public function isBalanced(): bool
    {
        return abs($this->calculatedVariance()) < 0.01;
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(PosRegister::class, 'pos_register_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'pos_shift_id');
    }
}
