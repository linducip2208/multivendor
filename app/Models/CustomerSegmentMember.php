<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_segment_id', 'customer_id', 'lifetime_value', 'order_count', 'last_order_at'])]
class CustomerSegmentMember extends Model
{
    protected function casts(): array
    {
        return [
            'lifetime_value' => 'decimal:2',
            'order_count' => 'integer',
            'last_order_at' => 'datetime',
        ];
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(CustomerSegment::class, 'customer_segment_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
