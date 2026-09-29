<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'code', 'name', 'email', 'status', 'commission_rate', 'total_commission', 'total_orders', 'total_revenue', 'approved_at'])]
class Affiliate extends Model
{
    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'total_commission' => 'decimal:2',
            'total_revenue' => 'decimal:2',
            'total_orders' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }
}
