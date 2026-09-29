<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['affiliate_id', 'customer_id', 'landing_path', 'ip_address', 'user_agent', 'converted_order_id', 'converted_at'])]
class AffiliateClick extends Model
{
    protected function casts(): array
    {
        return [
            'converted_at' => 'datetime',
        ];
    }

    public function isConverted(): bool
    {
        return $this->converted_order_id !== null;
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }
}
